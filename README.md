Tripaldock is a command line tool that helps with creating and running Tripal sites using docker.

## Installation
It is preferable to install this tool using composer.

```bash
composer global require statonlab/tripaldock
```

## Updating
You can update tripaldock using your local tripaldock!
```bash
./tripaldock self-update
```

## Documentation

### Creating a new tripal site
Using the `new` command, you can create a fresh Tripal 3 site. The command will automatically
download and install the dependencies as well as prepare Drupal and Tripal.
```bash
# Create a new site and call it site_name
tripaldock new site_name
```
Please note that the parameter site_name is also going to be the name of your database.

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

#### Remove and Destroy
If you would like to completely remove the container from the system including anonymous volumes, run the rm command.
```bash
# You will be prompted to confirm the action
./tripaldock rm
```

## License
Copyright 2017 University of Tennessee Knoxville ([MIT](LICENSE))
