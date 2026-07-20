# Chuckify

## Preparation

To run this application you need to have installed:

* PHP 8.2 or higher
* Composer
* Docker Compose
* node.js and npm
* Symfony CLI

## Installation

1. Checkout from git and change files to your needs.
2. Run `composer install`
3. Run `npm install`
4. Run `docker-compose up -d`
5. Prepare database by running
   1. `symfony console doctrine:database:drop --force`
   2. `symfony console doctrine:database:create`
   3. `symfony console doctrine:migrations:migrate`
6. If you want to setup a first user run `symfony console: doctrine:fixture:load`

## Run application

1. If not allready started run `docker-compose up -d`
2. Generate Javascripts by running `npm run dev`
3. Start webserver with `symfony server:start -d`
4. Enjoy `http://localhost:8000` in your browser
5. Login with `chuck@local.wip` and password `Norris`
