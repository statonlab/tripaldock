<?php

namespace StatonLab\TripalDock;

use StatonLab\TripalDock\Exceptions\SystemException;
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
     * @var \StatonLab\TripalDock\System
     */
    protected $system;

    /**
     * Define the command.
     */
    public function configure()
    {
        $this->setName('new')
            ->addArgument('name', InputArgument::REQUIRED, 'Name of your site.')
            ->setDescription('Create a new Tripal 3 site.');
    }

    /**
     * Create a new site.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @throws \Exception
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
        $profile = $this->io->choice('Which type of install would you like? Please choose one of the following:',
            $profiles);
        $selected_profile = intval(array_search($profile, $profiles));

        // Create the mapping dir
        mkdir('modules');
        mkdir('themes');

        if ($selected_profile === 0) {
            $this->basicInstall();
        } else {
            $this->fullInstall();
        }

        // Move back to working directory
        chdir($cwd);
    }

    /**
     * Execute a command and detect errors.
     * Exits the program if a problem occurs.
     *
     * @param $cmd
     * @param bool $ignore_errors
     * @throws \Exception
     */
    protected function exec($cmd, $ignore_errors = false)
    {
        if (! $this->system) {
            $this->system = new System();
        }

        try {
            $this->system->exec($cmd);

            return true;
        } catch (SystemException $exception) {
            $this->io->error($exception->getMessage());

            if (! $ignore_errors) {
                exit(1);
            }

            return false;
        }
    }

    /**
     * Installs the db using a pre-generated SQL dump.
     *
     * @throws \Exception
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
        //$this->getDependencies();
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
        //$this->downloadGeneralModules();
        $this->progressAdvance();

        // Install DB From SQL
        $this->installDBFromSQL();
        $this->progressAdvance();

        // Enable all modules
        // $this->enableGeneralModules();

        // Change the site name from full install to something else
        $this->setSiteName();
        $this->progressAdvance();

        $this->displaySuccessMessage();

        $this->io->success('You chose "Basic Install". Please make sure to run `./tripaldock drush updatedb -y` to update your database.');
    }

    /**
     * Create settings file.
     *
     * @throws \Exception
     */
    protected function createSettingsFile()
    {
        $this->io->text('Creating settings.php');

        $this->exec("docker-compose exec app bash -c \"sed -i 's/DB_NAME_PLACEHOLDER/{$this->siteName}/g' /drupal.settings.php && mv /drupal.settings.php /var/www/html/sites/default/settings.php\"");
    }

    /**
     * Install DB Dump
     *
     * @throws \Exception
     */
    protected function installDBFromSQL()
    {
        $sql_file = BASE_DIR.'/docker-files/docker/app/basic_install.sql';
        $this->io->text('TRIPALDOCK: Installing Database');
        $this->exec("docker-compose run --rm -e PGPASSWORD=secret postgres psql --quiet -U tripal -d {$this->siteName} -h postgres < $sql_file");
        $this->exec("docker-compose exec app drush updatedb -y");
        $this->exec("docker-compose exec app bash -c \"mkdir /var/www/html/sites/default/files && chown apache:apache /var/www/html/sites/default/files && chmod 755 /var/www/html/sites/default/files\"");
    }

    /**
     * Set the site name.
     *
     * @throws \Exception
     */
    protected function setSiteName()
    {
        $this->exec("docker-compose exec app bash -c \"drush variable-set site_name '{$this->siteName}' --root=/var/www/html\"",
            true);
        $this->exec("docker-compose exec app bash -c \"drush variable-set site_slogan 'Build simple dev environments' --root=/var/www/html\"",
            true);
    }

    /**
     * Full install including preparing chado and site.
     *
     * @throws \Exception
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
        // $this->getDependencies();
        $this->progressAdvance();

        // Publish docker files
        $this->publishDockerFiles();
        $this->progressAdvance();

        // Start up the container
        $this->start();
        $this->progressAdvance();

        // Sleep to make sure all containers are up
        sleep(10);

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
        $this->io->success("Tripal installed successfully! Visit http://localhost:{$this->port} to see your new site. The admin username is tripal and the password is secret.");
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
     *
     * @throws \Exception
     */
    protected function getDependencies()
    {
        $this->io->text('TRIPALDOCK: Downloading dependencies ...');
        $this->exec('wget https://www.drupal.org/files/projects/drupal-7.56.tar.gz');
        $this->exec('tar -zxvf drupal-7.56.tar.gz');
        $this->exec('mv drupal-7.56/* ./');
        $this->exec('mv drupal-7.56/.editorconfig ./');
        $this->exec('mv drupal-7.56/.gitignore ./');
        $this->exec('mv drupal-7.56/.htaccess ./');
        rmdir('drupal-7.56');
        unlink('drupal-7.56.tar.gz');
    }

    /**
     * Copy docker files over to the new site.
     *
     * @throws \Exception
     */
    protected function publishDockerFiles()
    {
        $this->io->text('TRIPALDOCK: Setting up docker ...');
        $dockerDirectory = BASE_DIR.'/docker-files/docker';
        $dockerComposeFile = BASE_DIR.'/docker-files/docker-compose.yaml';
        $this->exec("cp -r $dockerDirectory ./");
        $this->exec("cp $dockerComposeFile ./");
        $content = file_get_contents($this->directory.'/docker-compose.yaml');
        $content = str_replace('- POSTGRES_DB=tripal', "- POSTGRES_DB={$this->siteName}",
            $content);
        $content = str_replace('- "3000:80"', "- \"{$this->port}:80\"", $content);
        $content = str_replace('- "9200:9200"', "- \"{$this->ESPort}:9200\"", $content);
        file_put_contents($this->directory.'/docker-compose.yaml', $content);
    }

    /**
     * Start docker.
     *
     * @throws \Exception
     */
    protected function start()
    {
        $this->io->text('TRIPALDOCK: Starting docker ...');
        $cwd = getcwd();
        chdir($cwd.'/docker');
        $this->exec('docker-compose up -d --build');
        $this->exec('docker-compose exec app bash -c "source ~/.bashrc"');
        chdir($cwd);
    }

    /**
     * Install Drupal.
     *
     * @throws \Exception
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
        $this->exec("docker-compose exec app bash -c \"cd /var/www/html && cp /default.settings.php sites/default/. && drush si {$options} && drush cc all\"");
        chdir($cwd);
    }

    /**
     * Install modules.
     *
     * @throws \Exception
     */
    protected function installTripal()
    {
        $this->io->text('TRIPALDOCK: Installing Tripal');

        $this->io->text('TRIPALDOCK: Downloading Tripal:7.x-3.x development version');
        $this->downloadTripal();

        $this->io->text('TRIPALDOCK: Enabling Tripal');
        $this->enableTripal();

        //$this->io->text('TRIPALDOCK: Applying patches');
        //$this->applyPatches();

        $this->io->text('TRIPALDOCK: Preparing chado');
        $this->prepareChado();

        $this->io->text('TRIPALDOCK: Creating content types');
        $this->prepareSite();

        $this->io->text('TRIPALDOCK: Enabling general modules');
        // $this->downloadGeneralModules();
        $this->enableGeneralModules();

        $this->io->text('TRIPALDOCK: Configuring permissions');
        $this->configurePermissions();
    }

    /**
     * Download general modules.
     *
     * @throws \Exception
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
            'admin_menu',
        ];
        $enable = implode(' ', $enable_array);
        $cwd = getcwd();
        chdir($cwd.'/docker');
        $this->exec("docker-compose exec app bash -c \"cd /var/www/html && drush dl -y {$enable}\"");
        chdir($cwd);
    }

    /**
     * Enable dependency modules
     *
     * @throws \Exception
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
        $this->exec("docker-compose exec app bash -c \"drush en -y {$enable}\"");
        $this->exec("docker-compose exec app bash -c \"drush dis -y {$disable}\"");
        chdir($cwd);
    }

    /**
     * Download Tripal:7.x-3.x
     *
     * @throws \Exception
     */
    protected function downloadTripal()
    {
        $cwd = getcwd();
        chdir($cwd.'/modules');
        $this->exec('git clone https://github.com/tripal/tripal.git');
        chdir($cwd);
    }

    /**
     * Download and apply patches.
     *
     * @throws \Exception
     */
    protected function applyPatches()
    {
        $cwd = getcwd();
        $this->exec('wget --no-check-certificate https://drupal.org/files/drupal.pgsql-bytea.27.patch');
        $this->exec('patch -p1 < drupal.pgsql-bytea.27.patch');
        chdir($cwd.'/sites/all/modules/views');
        $this->exec('patch -p1 < ../tripal/tripal_chado_views/views-sql-compliant-three-tier-naming-1971160-30.patch');
        chdir($cwd);
    }

    /**
     * Enable Tripal.
     *
     * @throws \Exception
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
        $this->exec("docker-compose exec app bash -c \"cd /var/www/html && drush --root=/var/www/html en {$options} -y\"");
        chdir($cwd);
    }

    /**
     * Prepare chado.
     *
     * @throws \Exception
     */
    protected function prepareChado()
    {
        $cmd = "module_load_include('inc', 'tripal_chado', 'includes/tripal_chado.install'); tripal_chado_load_drush_submit('Install Chado v1.3');";
        $cwd = getcwd();
        chdir($cwd.'/docker');
        $exit_code_1 = $this->exec("docker-compose exec app bash -c \"cd /var/www/html && drush --root=/var/www/html eval \\\"{$cmd}\\\"\"",
            true);
        $cmd = "module_load_include('inc', 'tripal', 'tripal.drush'); drush_tripal_trp_run_jobs_install('tripal');";
        $exit_code_2 = $this->exec("docker-compose exec app bash -c \"drush --root=/var/www/html eval \\\"{$cmd}\\\" && drush --root=/var/www/html trp-run-jobs --username=tripal\"",
            true);
        chdir($cwd);
        if ($exit_code_1 === false || $exit_code_2 === false) {
            $this->io->error("TRIPALDOCK: Unable to prepare chado. Please visit your site and follow the instructions to prepare chado.");
        }
    }

    /**
     * Prepare site by creating content types.
     *
     * @throws \Exception
     */
    protected function prepareSite()
    {
        $cmd = "module_load_include('inc', 'tripal_chado', 'includes/setup/tripal_chado.setup'); tripal_chado_prepare_drush_submit();";
        $cwd = getcwd();
        chdir($cwd.'/docker');
        $exit_code = $this->exec("docker-compose exec app bash -c \"drush --root=/var/www/html eval \\\"{$cmd}\\\" && drush --root=/var/www/html trp-run-jobs --username=tripal\"");
        if ($exit_code === false) {
            $this->io->error("TRIPALDOCK: Unable to prepare site! Please prepare the site by following Tripal's instructions.");
        }
        chdir($cwd);
    }

    /**
     * Configure content permissions for administrators.
     *
     * @throws \Exception
     */
    protected function configurePermissions()
    {
        $cwd = getcwd();
        chdir($cwd.'/docker');
        $exit_code = $this->exec("docker-compose exec app bash -c \"php /configure-permissions.php {$this->siteName}\"",
            true);
        if ($exit_code === false) {
            $this->io->error('TRIPALDOCK: Permissions were not configured correctly. Please visit your site and fix them manually using the admin pages.');
        }
        chdir($cwd);
    }

    /**
     * Publish the local version of tripaldock.
     *
     * @throws \Exception
     */
    protected function publishLocalTripaldock()
    {
        copy(BASE_DIR.'/docker-files/tripaldock-bash', $this->directory.'/tripaldock');
        $this->exec('chmod +x '.$this->directory.'/tripaldock');
    }

    /**
     * Install fields generator.
     *
     * @throws \Exception
     */
    protected function installPackages()
    {
        $cwd = getcwd();
        chdir($cwd.'/docker');
        $this->exec("docker-compose exec app bash -c \"composer global require statonlab/fields_generator\"");
        chdir($cwd);
    }
}
