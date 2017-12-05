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

        // Start progress bar
        $this->io->progressStart(8);

        // Create the site directory
        $this->io->text("TRIPALDOCK: Creating {$this->siteName}");
        if (! mkdir($this->directory)) {
            $this->io->error("TRIPALDOCK: Could not create {$this->siteName}. Make sure file does not already exist.");

            return;
        }
        chdir($this->directory);
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

        $this->publishLocalTripaldock();
        $this->progressAdvance();

        // Inform user of working port and commands
        $this->io->success(
            "Tripal installed successfully! Visit http://localhost:{$this->port} to see your new site. The admin username is tripal and the password is secret."
        );

        // Move back to working directory
        chdir($cwd);
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
        system('docker-compose up -d');
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
            "docker-compose exec app bash -c \"drush si {$options} && drush cc all\""
        );
        chdir($cwd);
        system("curl -XGET \"localhost:{$this->port}\"");
    }

    /**
     * Install modules.
     */
    protected function installTripal()
    {
        $this->io->text('TRIPALDOCK: Installing Tripal');

        $this->io->text('TRIPALDOCK: Downloading Tripal:7.x-3.x development version');
        $this->downloadTripal();

        $this->io->text('TRIPALDOCK: Enabling general modules');
        $this->enableGeneralModules();

        $this->io->text('TRIPALDOCK: Enabling Tripal');
        $this->enableTripal();

        $this->io->text('TRIPALDOCK: Applying patches');
        $this->applyPatches();

        $this->io->text('TRIPALDOCK: Preparing chado');
        $this->prepareChado();

        $this->io->text('TRIPALDOCK: Creating content types');
        $this->prepareSite();

        $this->io->text('TRIPALDOCK: Configuring permissions');
        $this->configurePermissions();
    }

    /**
     * Enable dependency modules
     */
    protected function enableGeneralModules()
    {
        $options = [
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
        ];
        $options = implode(' ', $options);
        $cwd = getcwd();
        chdir($cwd.'/docker');
        system(
            "docker-compose exec app bash -c \"drush en -y {$options}\""
        );
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
            "docker-compose exec app bash -c \"cd /var/www/html && drush --root=/var/www/html eval \\\"{$cmd}\\\"\""
        );
        $cmd = "module_load_include('inc', 'tripal', 'tripal.drush'); drush_tripal_trp_run_jobs_install('tripal');";
        system(
            "docker-compose exec app bash -c \"drush --root=/var/www/html eval \\\"{$cmd}\\\" && drush --root=/var/www/html trp-run-jobs --user=tripal\""
        );
        chdir($cwd);
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
            "docker-compose exec app bash -c \"drush --root=/var/www/html eval \\\"{$cmd}\\\" && drush --root=/var/www/html trp-run-jobs --user=tripal\""
        );
        chdir($cwd);
    }

    /**
     * Configure content permissions for administrators.
     */
    protected function configurePermissions()
    {
        $cwd = getcwd();
        chdir($cwd.'/docker');
        system("docker-compose exec app bash -c \"php /configure-permissions.php {$this->siteName}\"");
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
}