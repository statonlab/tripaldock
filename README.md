[![DOI](https://zenodo.org/badge/112856705.svg)](https://zenodo.org/badge/latestdoi/112856705)

Tripaldock is a command line tool that helps with creating and running Tripal sites using docker. This tool is designed for developers and is not suitable for production. It utilizes Docker Compose to build a stack of configured containers to host all required services.
Supported services:
- Web Server (Apache2)
- PHP (7.1)
- PostgreSQL (9.6)
- Elasticsearch (5.6)

## Installation
It is preferable to install this tool using composer.

```bash
composer global require statonlab/tripaldock:~0.1.1
```

## Updating
You can update tripaldock using your local tripaldock!
```bash
./tripaldock self-update
```

## Documentation

### Required Software
- [Composer](https://getcomposer.org)
- [Docker](https://docs.docker.com/install)
- [Docker compose](https://docs.docker.com/compose/install)

### Creating a new tripal site
Using the `new` command, you can create a fresh Tripal 3 site. The command will automatically
download and install the dependencies as well as prepare Drupal and Tripal.
```bash
# Create a new site and call it site_name
tripaldock new site_name

# Navigate the new site
cd site_name
```
Please note that the parameter site_name is also going to be the name of your database.

### Admin Credentials

These are the default credentials that tripaldock uses for the admin user:

- **Username**: tripal
- **Password**: secret

#### Site Structure
Once tripaldock is done creating your new site, a new directory (site_name) will be created.
The directory contains multiple folders along with docker related files. The folders are:
- modules: Maps to sites/all/modules/custom and should contain your custom modules. By default, this folder will have `tripal` in it.
- themes: Maps to sites/all/themes and should contain your custom themes.
- libraries: Maps to sites/all/libraries and should contain any Drupal libraries.
- files: Maps to sites/default/files and should hold any custom files.

### Local Tripaldock
Once the installation of the new site is completed, a copy of `tripaldock` will be placed within the resulting file.
This is your site's specific tripaldock. It provides a set of commands to interact directly with the container responsible
for this site.

#### Up and Down
To start up the container:
```bash
./tripaldock up
```

To stop the container:
```bash
./tripaldock down
```

#### SSH
To access your container and run commands directly within it, you may use the ssh command. This command will take you
directly to `/var/www/html` which is where your Drupal resides. From there, you can run any command such as `drush`
and interact with the database using `psql -U tripal`. 
```bash
./tripaldock ssh
```
Which is equivalent to running:
```bash
docker-compose exec app bash
```

#### Obtaining Logs
You can use the `logs` command to obtain apache, php, postgres and elasticsearch logs:
```bash
./tripaldock logs # Get all available logs
./tripaldock logs app # Get apache and php logs
./tripaldock logs elasticsearch # Get elasticsearch logs
./tripaldock logs postgres # Get DB logs
```

#### Installing Drupal Modules
TripalDock provides a special install command to pull known modules directly from git and use composer to install
their library dependencies if available. However, if the module is not one of the listed below, it will use drush
to attempt to install the module.
```bash
./tripaldock install [MODULE NAME]
```

Known modules:
- tripal_elasticsearch: installs the module along with elasticsearch-php library

#### Running Drush
You can also use tripaldock to run drush without having to access the container:
```bash
./tripaldock drush [ARGS]
```

#### Connecting to Elasticsearch
The tripal_elasticsearch module will require you to setup the correct hostname and port for your elasticsearch server.
To use the elasticsearch that ships with this module, you should do the following:
- Visit `/admin/tripal/extension/tripal_elasticsearch/connection`
- Enter `http://elasticsearch` in the host field. You can omit the port.
- Click the submit button.

#### Remove and Destroy
If you would like to completely remove the container from the system including anonymous volumes, run the rm command.
```bash
# You will be prompted to confirm the action
./tripaldock rm
```
## Drupal Security

Drupal 7 is periodically updated with important security patches.  You should ensure that you keep your core Drupal software up to date and [visit the Drupal release website](https://www.drupal.org/project/drupal) for more information.

## License
Copyright 2017 University of Tennessee Knoxville ([GPL](LICENSE))
