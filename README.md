Even though apigenerator.org has been discontinued this fork is still in use
by the Neos project and maintained by me.

Install
-------

Clone the project on your server and install dependencies.

```bash
git clone git@github.com:kdambekalns/apigenerator.org.git /path/to/apigenerator.org
cd /path/to/apigenerator.org
curl -sS https://getcomposer.org/installer | php
php composer.phar install --no-dev
```

Now decide on an actual generator to use:

```bash
php composer.phar require apigen/apigen
```

or

```bash
php composer.phar require phpdocumentor/phpdocumentor
```

Setup your web server to point to `/path/to/apigenerator.org/web`.

Setup ssh key
-------------

As your website user run `ssh-keygen` and generate a new ssh key pair.
Add the public key to your github profile.

Hint: Make sure, the private key is protected against external access!

Setup git
---------

As your website user
run `git config --global user.email "info@my-apigen-hook.org"`
and `git config --global user.name "My Name"`
to configure commit messages.
