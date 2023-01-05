Kaabar JWT Auth Extension
=========================
PHP JWT (JSON Web Token) is a library that allows developers to create and validate JWTs (JSON Web Tokens) in PHP. A JWT is a compact, URL-safe way to represent claims that can be transferred between two parties. It is typically used to securely transmit information between a server and a client, such as an API request or a user login session.  The PHP JWT library provides functions for creating JWTs with a variety of options, including custom claims and algorithms for signing and verifying the JWT. It also includes support for common JWT libraries such as JWT-PHP and firebase/php-jwt.  One of the benefits of using JWTs with PHP is that they can be easily integrated into web applications and APIs. They allow for secure communication between the server and client without the need for storing session data on the server, making them ideal for stateless applications.  Overall, PHP JWT is a useful tool for developers looking to implement JWT authentication and authorization in their PHP projects.


Installation Step
-----------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist kaabar-jwt/yii2-jwt:dev-master
```

or add

```
"kaabar-jwt/yii2-jwt": "dev-master"
```

to the require section of your `composer.json` file.


Usage
-----

Once the extension is installed, simply use it in your code by  :

```php
<?= \kaabar\jwt\AutoloadExample::widget(); ?>```