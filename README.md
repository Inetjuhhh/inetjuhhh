<p align="center"><img src="storage/img/ine.jpg" alt="ine-avatar" width="50px" class="rounded-xl"></p>

<p align="center">
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# Inetjuhhh 
After all this years of no priority I decided to try and make my own blog instead of using the easy Wordpress template. If you like to get on this road on developing my blog with a Filament admin panel and a Tailwind frontend application. 
Go get along on my journey. Will probably take a while, but already have a basic, so no reason to hurry.

## Documentation
In this 'README.md`:
* [🔰Getting started] 
* [🔧Technologies and packages]
* [😵‍💫Issues] 
* [🤗Contributing] (docs/github-flow.md)


## 🔰 Getting started
### 1. Install prerequisites

Ensure you have installed:

* PHP 8.2 or higher *(for example, through [Laragon](https://laragon.org/index.html), [if necessary find how to update PHP here](https://pen-y-fan.github.io/2023/01/15/how-to-update-the-php-version-in-laragon/))*

* [Composer](https://getcomposer.org/)

* [Git (for Windows)](https://gitforwindows.org/) - *In the next steps we'll assume you'll use Git Bash as your choice of terminal.*

* MySQL 8.0 or higher

### 2. Download the project and dependencies

- Clone the repository: `git clone https://github.com/quizit

- Enter the project directory: `cd inetjuhhh`

- Run `composer install` *(to install PHP dependencies)*

- Run `npm install` *(to install JavaScript dependencies needed for building the front-end)*

### 3. Fill the environment file

- Copy `.env.example` to `.env`

- Fill the following fields in the `.env`:

    - The database credentials for the application, e.g:
        ```
        DB_CONNECTION=mysql
        DB_HOST=127.0.0.1
        DB_PORT=3306
        DB_DATABASE=inetjuhhh
        DB_USERNAME=root
        DB_PASSWORD=
        ```

    - The Google Maps API Key (ask another developer for this), e.g:
        ```
        GOOGLE_MAPS_API_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
        ```

- Generate an application key into `APP_KEY` by running `php artisan key:generate`

### 4. Run the migrations and seed the database

- Create a database named `inetjuhhh` for the application

- Run `php artisan migrate --seed` to create the database tables and seed them with data.

### 5. Get ready to run the application

- Run `npm run dev` to build the front-end assets *(leave this terminal running so changes are automatically compiled)*

- Run `php artisan serve` to start the application *(leave this terminal running so the application is served)*

- Open the application in your browser at [`http://127.0.0.1:8000`](http://127.0.0.1:8000)

- Login with any of the credentials found in the [seeder file](database/seeders/DatabaseSeeder.php)


## 🔧Technologies and Packages
In this application I am using the following software, frameworks and packages:
- php 8.2.17
- MySQL 8.0
- Vue
- Laravel 11.21.0
- Vite 5.0
- Tailwind 3.4.11
- Alpine js 3.4.2

## 😵‍💫 Issues
In my repository you can see what issues I am working on. Feel free to add issues if you think there is a feature you wouldn't want to miss in this application.

## 🤗 Contributing
Do you want to contribute to my project, feel free to contact me and give suggestions to make it a wicked quiz application.
Use the steps in our <a href="docs/images/github-flow.md">Github flow</a> to check how to commit.

