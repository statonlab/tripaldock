<?php
/**
 * Created by PhpStorm.
 * User: Almsaeed
 * Date: 12/3/17
 * Time: 11:37 AM
 */

namespace StatonLab\TripalDock;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class NewCommand extends Command
{
    /**
     * Name of site.
     *
     * @var string
     */
    protected $siteName;

    /**
     * Current working directory.
     *
     * @var string
     */
    protected $directory;

    /**
     * Input/Output.
     *
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * App Port.
     *
     * @var int
     */
    protected $port = 3000;

    /**
     * Elasticsearch port.
     *
     * @var int
     */
    protected $ESPort = 9200;

    /**
     * Define the command.
     */
    public function configure()
    {
        $this->setName('new')->addArgument('name', InputArgument::REQUIRED, 'Name of your site.')->setDescription(
            'Create a new Tripal 3 site.'
        );
    }

    /**
     * Create a new site.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        // Initialize
        $cwd = getcwd();
        $this->siteName = $input->getArgument('name');
        $this->directory = "{$cwd}/{$this->siteName}";
        $this->io = new SymfonyStyle($input, $output);

        // Create the site directory
        $this->io->text("TRIPALDOCK: Creating {$this->siteName}");
        if (! mkdir($this->directory)) {
            $this->io->error("TRIPALDOCK: Could not create {$this->siteName}. Make sure file does not already exist.");

            return;
        }
        chdir($this->directory);

        $profiles = [
            'Basic Install: uses a pre-generated sql to build the database [fastest]',
            'Full Install: installs everything from scratch [slowest]',
        ];
        $profile = $this->io->choice(
            'Which type of install would you like? Please choose one of the following:',
            $profiles
        );
        $selected_profile = intval(array_search($profile, $profiles));
        if ($selected_profile === 0) {
            $this->basicInstall();
        } else {
            $this->fullInstall();
        }

        // Move back to working directory
        chdir($cwd);
    }

    /**
     * Installs the db using a pre-generated SQL dump.
     */
    protected function basicInstall()
    {
        $this->io->progressStart(10);

        $this->selectPort();
        $this->progressAdvance();

        // Publish docker files
        $this->publishDockerFiles();
        $this->progressAdvance();

        // Publish bash script
        $this->publishLocalTripaldock();
        $this->progressAdvance();

        // Download all dependencies
        $this->getDependencies();
        $this->progressAdvance();

        // Bring up the images
        $this->start();
        $this->progressAdvance();

        // Download tripal
        $this->downloadTripal();
        $this->progressAdvance();

        // Create settings file on machine
        $this->createSettingsFile();
        $this->progressAdvance();

        // Download modules
        $this->downloadGeneralModules();
        $this->progressAdvance();

        // Install DB From SQL
        $this->installDBFromSQL();
        $this->progressAdvance();

        // Enable all modules
        $this->enableGeneralModules();
        $this->progressAdvance();

        $this->displaySuccessMessage();

        $this->io->success('You chose "Basic Install". Please make sure to run `./tripaldock drush updatedb -y` to update your database.');
    }

    /**
     * Create settings file.
     */
    protected function createSettingsFile()
    {
        $this->io->text('Creating settings.php');

        system(
            "docker-compose exec app bash -c \"sed -i 's/DB_NAME_PLACEHOLDER/{$this->siteName}/g' /drupal.settings.php && mv /drupal.settings.php /var/www/html/sites/default/settings.php\"",
            $exit_code
        );
    }

    /**
     * Install DB Dump
     */
    protected function installDBFromSQL()
    {
        $this->io->text('TRIPALDOCK: Installing Database');
        system(
            "docker-compose exec app bash -c \"PGPASSWORD=secret psql -U tripal -d {$this->siteName} -h postgres < /basic_install.sql\"",
            $exit_code
        );
    }

    /**
     * Full install including preparing chado and site.
     */
    protected function fullInstall()
    {
        // Start progress bar
        $this->io->progressStart(9);
        $this->progressAdvance();

        // Select port
        $this->selectPort();
        $this->progressAdvance();

        // Download dependencies
        $this->getDependencies();
        $this->progressAdvance();

        // Publish docker files
        $this->publishDockerFiles();
        $this->progressAdvance();

        // Start up the container
        $this->start();
        $this->progressAdvance();

        // Install drupal and enable tripal
        $this->installDrupal();
        $this->progressAdvance();

        $this->installTripal();
        $this->progressAdvance();

        // Publish TripalDock files
        $this->publishLocalTripaldock();
        $this->progressAdvance();

        // Install other packages
        $this->installPackages();
        $this->progressAdvance();

        $this->displaySuccessMessage();
    }

    protected function displaySuccessMessage()
    {
        // Inform user of working port and commands
        $this->io->success(
            "Tripal installed successfully! Visit http://localhost:{$this->port} to see your new site. The admin username is tripal and the password is secret."
        );
    }

    /**
     * Advance progress bar.
     *
     * @param int $step
     */
    protected function progressAdvance($step = 1)
    {
        $this->io->progressAdvance($step);
        $this->io->writeln("\n");
    }

    /**
     * Select an unopened port to use.
     */
    protected function selectPort()
    {
        $this->io->text("TRIPALDOCK: Selecting app port");
        do {
            $file = @fsockopen('localhost', $this->port);
            if ($file) {
                $this->io->text("TRIPALDOCK: Port {$this->port} is in use incrementing to ".(++$this->port));
            }
        } while ($file);
        $this->io->text("TRIPALDOCK: Using port {$this->port}");

        $this->io->text("TRIPALDOCK: Selecting elasticsearch port");
        do {
            $file = @fsockopen('localhost', $this->ESPort);
            if ($file) {
                $this->io->text("TRIPALDOCK: Port {$this->ESPort} is in use incrementing to ".(++$this->ESPort));
            }
        } while ($file);
        $this->io->text("TRIPALDOCK: Using port {$this->ESPort} for elasticsearch");
    }

    /**
     * Download drupal and tripal.
     */
    protected function getDependencies()
    {
        $this->io->text('TRIPALDOCK: Downloading dependencies ...');
        system('wget https://www.drupal.org/files/projects/drupal-7.56.tar.gz');
        system('tar -zxvf drupal-7.56.tar.gz');
        system('mv drupal-7.56/* ./');
        system('mv drupal-7.56/.editorconfig ./');
        system('mv drupal-7.56/.gitignore ./');
        system('mv drupal-7.56/.htaccess ./');
        rmdir('drupal-7.56');
        unlink('drupal-7.56.tar.gz');
    }

    /**
     * Copy docker files over to the new site.
     */
    protected function publishDockerFiles()
    {
        $this->io->text('TRIPALDOCK: Setting up docker ...');
        $dockerDirectory = BASE_DIR.'/docker-files/docker';
        $dockerComposeFile = BASE_DIR.'/docker-files/docker-compose.yaml';
        system("cp -r $dockerDirectory ./");
        system("cp $dockerComposeFile ./");
        $content = file_get_contents($this->directory.'/docker-compose.yaml');
        $content = str_replace('- POSTGRES_DB=tripal', "- POSTGRES_DB={$this->siteName}", $content);
        $content = str_replace('- "3000:80"', "- \"{$this->port}:80\"", $content);
        $content = str_replace('- "9200:9200"', "- \"{$this->ESPort}:9200\"", $content);
        file_put_contents($this->directory.'/docker-compose.yaml', $content);
    }

    /**
     * Start docker.
     */
    protected function start()
    {
        $this->io->text('TRIPALDOCK: Starting docker ...');
        $cwd = getcwd();
        chdir($cwd.'/docker');
        system('docker-compose up -d --build');
        system('docker-compose exec app bash -c "source ~/.bashrc"');
        chdir($cwd);
    }

    /**
     * Install Drupal.
     */
    protected function installDrupal()
    {
        $this->io->text('TRIPALDOCK: Installing Drupal');
        $name = escapeshellarg($this->siteName);
        $options = [
            "standard",
            "install_configure_form.update_status_module='array(FALSE,FALSE)'",
            "--db-url=pgsql://tripal:secret@postgres:5432/{$name}",
            "--account-name=tripal",
            "--account-pass=secret",
            "--site-mail=test@example.com",
            "--site-name={$name}",
            "-y",
        ];
        $options = implode(' ', $options);
        $cwd = getcwd();
        chdir($cwd.'/docker');
        system(
            "docker-compose exec app bash -c \"drush si {$options} && drush cc all\"",
            $exit_code
        );
        $this->handleSystemReturn($exit_code);
        chdir($cwd);
    }

    /**
     * Install modules.
     */
    protected function installTripal()
    {
        $this->io->text('TRIPALDOCK: Installing Tripal');

        $this->io->text('TRIPALDOCK: Downloading Tripal:7.x-3.x development version');
        $this->downloadTripal();

        $this->io->text('TRIPALDOCK: Enabling Tripal');
        $this->enableTripal();

        $this->io->text('TRIPALDOCK: Applying patches');
        $this->applyPatches();

        $this->io->text('TRIPALDOCK: Preparing chado');
        $this->prepareChado();

        $this->io->text('TRIPALDOCK: Creating content types');
        $this->prepareSite();

        $this->io->text('TRIPALDOCK: Enabling general modules');
        $this->downloadGeneralModules();
        $this->enableGeneralModules();

        $this->io->text('TRIPALDOCK: Configuring permissions');
        $this->configurePermissions();
    }

    /**
     * Download general modules.
     */
    protected function downloadGeneralModules()
    {
        $enable_array = [
            'ctools',
            'date',
            'devel',
            'ds',
            'link',
            'entity',
            'libraries',
            'redirect',
            'token',
            'uuid',
            'jquery_update',
            'views',
            'webform',
            'field_group',
            'field_group_table',
            'field_formatter_class',
            'field_formatter_settings',
            'views_ui',
            'admin_menu'
        ];
        $enable = implode(' ', $enable_array);
        $cwd = getcwd();
        chdir($cwd.'/docker');
        system("docker-compose exec app bash -c \"drush dl -y {$enable}\"");
        chdir($cwd);
    }

    /**
     * Enable dependency modules
     */
    protected function enableGeneralModules()
    {
        $enable_array = [
            'devel',
            'libraries',
            'jquery_update',
            'admin_menu',
        ];
        $enable = implode(' ', $enable_array);

        $disable = [
            'toolbar',
            'overlay',
        ];
        $disable = implode(' ', $disable);

        $cwd = getcwd();
        chdir($cwd.'/docker');
        system("docker-compose exec app bash -c \"drush en -y {$enable}\"");
        system("docker-compose exec app bash -c \"drush dis -y {$disable}\"");
        chdir($cwd);
    }

    /**
     * Download Tripal:7.x-3.x
     */
    protected function downloadTripal()
    {
        $cwd = getcwd();
        chdir($cwd.'/sites/all/modules');
        system('git clone https://github.com/tripal/tripal.git');
        chdir($cwd);
    }

    /**
     * Download and apply patches.
     */
    protected function applyPatches()
    {
        $cwd = getcwd();
        system('wget --no-check-certificate https://drupal.org/files/drupal.pgsql-bytea.27.patch');
        system('patch -p1 < drupal.pgsql-bytea.27.patch');
        chdir($cwd.'/sites/all/modules/views');
        system('patch -p1 < ../tripal/tripal_chado_views/views-sql-compliant-three-tier-naming-1971160-30.patch');
        chdir($cwd);
    }

    /**
     * Enable Tripal.
     */
    protected function enableTripal()
    {
        $options = [
            'tripal',
            'tripal_chado',
            'tripal_ds',
            'tripal_ws',
        ];
        $options = implode(', ', $options);
        $cwd = getcwd();
        chdir($cwd.'/docker');
        system(
            "docker-compose exec app bash -c \"cd /var/www/html && drush --root=/var/www/html en {$options} -y\""
        );
        chdir($cwd);
    }

    /**
     * Prepare chado.
     */
    protected function prepareChado()
    {
        $cmd = "module_load_include('inc', 'tripal_chado', 'includes/tripal_chado.install'); tripal_chado_load_drush_submit('Install Chado v1.3');";
        $cwd = getcwd();
        chdir($cwd.'/docker');
        system(
            "docker-compose exec app bash -c \"cd /var/www/html && drush --root=/var/www/html eval \\\"{$cmd}\\\"\"",
            $exit_code_1
        );
        $cmd = "module_load_include('inc', 'tripal', 'tripal.drush'); drush_tripal_trp_run_jobs_install('tripal');";
        system(
            "docker-compose exec app bash -c \"drush --root=/var/www/html eval \\\"{$cmd}\\\" && drush --root=/var/www/html trp-run-jobs --username=tripal\"",
            $exit_code_2
        );
        chdir($cwd);
        if (intval($exit_code_1) !== 0 || intval($exit_code_2) !== 0) {
            $this->io->error(
                "TRIPALDOCK: Unable to prepare chado. Please visit your site and follow the instructions to prepare chado."
            );
        }
    }

    /**
     * Prepare site by creating content types.
     */
    protected function prepareSite()
    {
        $cmd = "module_load_include('inc', 'tripal_chado', 'includes/setup/tripal_chado.setup'); tripal_chado_prepare_drush_submit();";
        $cwd = getcwd();
        chdir($cwd.'/docker');
        system(
            "docker-compose exec app bash -c \"drush --root=/var/www/html eval \\\"{$cmd}\\\" && drush --root=/var/www/html trp-run-jobs --username=tripal\"",
            $exit_code
        );
        if (intval($exit_code) !== 0) {
            $this->io->error(
                "TRIPALDOCK: Unable to prepare site! Please prepare the site by following Tripal's instructions."
            );
        }
        chdir($cwd);
    }

    /**
     * Configure content permissions for administrators.
     */
    protected function configurePermissions()
    {
        $cwd = getcwd();
        chdir($cwd.'/docker');
        system("docker-compose exec app bash -c \"php /configure-permissions.php {$this->siteName}\"", $exit_code);
        if (intval($exit_code) !== 0) {
            $this->io->error(
                'TRIPALDOCK: Permissions were not configured correctly. Please visit your site and fix them manually using the admin pages.'
            );
        }
        chdir($cwd);
    }

    /**
     * Publish the local version of tripaldock.
     */
    protected function publishLocalTripaldock()
    {
        copy(BASE_DIR.'/docker-files/tripaldock-bash', $this->directory.'/tripaldock');
        system('chmod +x '.$this->directory.'/tripaldock');
    }

    /**
     * Install fields generator.
     */
    protected function installPackages()
    {
        $cwd = getcwd();
        chdir($cwd.'/docker');
        system("docker-compose exec app bash -c \"composer global require statonlab/fields_generator\"");
        chdir($cwd);
    }

    protected function handleSystemReturn($value)
    {
        $value = intval($value);
        if ($value !== 0) {
            throw new \Exception("Exited with code $value");
        }
    }
}