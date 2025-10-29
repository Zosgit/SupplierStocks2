# Supplier Stock Processing

This project is a small Symfony-based backend application designed to process supplier stock files and expose their data through a REST API returning JSON responses.

The application provides a complete backend solution for managing supplier stock data.  
It allows importing and transforming CSV files from different suppliers (each with its own format) and storing them in a MySQL database using Doctrine ORM.  
A REST API endpoint `/get-stocks` enables anonymous access to stock information filtered by `ean` or `mpn`.  
The project runs in a Docker-based environment and includes unit and functional tests implemented with PHPUnit, WebTestCase and Zenstruck Foundry.

## Technologies
- PHP 8.2.29
- Symfony 6.4.26
- MySQL
- Docker & docker-compose
- Composer 2.8.12
- Doctrine ORM
- PHPUnit, Zenstruck Foundry

## How to run (using Docker)

1. Clone the repository
    ```bash
   git clone "https://github.com/Zosgit/SupplierStocks2.git"
   cd SupplierStocks2
2. Build and start containers
    ```bash
    docker-compose up -d
3. Install dependencies
    ```bash
    docker-compose exec symfony-app2 composer install
4. Run database and migrations
    ```bash
    docker-compose exec symfony-app2 php bin/console doctrine:database:create
    docker-compose exec symfony-app2 php bin/console doctrine:migrations:migrate
5. Import supplier CSV data
    ```bash
    docker-compose exec symfony-app2 php bin/console app:import:stock /app/symfony/data/lorotom.csv Lorotom
    docker-compose exec symfony-app2 php bin/console app:import:stock /app/symfony/data/trah.csv Trah
6. Access the API

    You can test the API after importing data:

- Search by EAN only
    ```bash
    http://localhost:8080/get-stocks?ean=0007561489

- Search by mpn only
    ```bash
    http://localhost:8080/get-stocks?mpn=91103000

- Search by both ean and mpn
    ```bash
    http://localhost:8080/get-stocks?ean=0007561489&mpn=91103000

- No parameters provided 
    ```bash
    http://localhost:8080/get-stocks
7. Run tests
    ```bash
    docker-compose exec app php bin/phpunit --testdox