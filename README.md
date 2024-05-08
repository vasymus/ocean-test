## About

Test task for Ocean.

Is based on [Laravel Framework](https://laravel.com).

### Local deploy

Just git pull from repository, install docker-compose (if not already have).

Be sure that port 80 is not in used. If it is, create file .env based on .env.example and change variable DOCKER_APP_PORT to any suitable another port (for example, 9999).

Than run:

```shell
docker-compose up -d --build
docker-compose exec app bash
composer install
composer dump-autoload -o
cp .env.example .env
php artisan key:generate
php artisan migrate
```

After that use browser or postman or any other tool to check API.

For example: http://localhost/api/v1/health-check . Do not forget to put according port (localhost:DOCKER_APP_PORT) if not 80.
