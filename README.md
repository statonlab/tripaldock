Tripaldock is a command line tool that helps with creating and running Tripal sites using docker.

## Installation
It is preferable to install this tool using composer.

```
composer global require statonlab/tripaldock
```

## Documentation

### Creating a new tripal site
Using the `new` command, you can create a fresh Tripal 3 site. The command will automatically
download and install the dependencies as well as prepare Drupal and Tripal.
```
# Create a new site and call it site_name
tripaldock new site_name
```
Please note that the parameter site_name is also going to be the name of your database.

### Local Tripaldock
Once the installation of the new site is completed, a copy of `tripaldock` will be placed within the resulting file.
This is your site's specific tripaldock. It provides a set of commands to interact directly with the container responsible
for this site.

#### SSH
To access your container and run commands directly within it, you may use the ssh command. This command will take you
directly to `/var/www/html` which is where your Drupal resides. From there, you can run any command such as `drush`
and interact with the database using `psql -U tripal`. 
```
./tripaldock ssh
```

## License
Copyright 2017 University of Tennessee Knoxville ([MIT](LICENSE))
