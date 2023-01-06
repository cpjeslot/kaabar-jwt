Kaabar JWT Auth Extension
=========================
PHP JWT (JSON Web Token) is a library that allows developers to create and validate JWTs (JSON Web Tokens) in PHP. A JWT is a compact, URL-safe way to represent claims that can be transferred between two parties. It is typically used to securely transmit information between a server and a client, such as an API request or a user login session.  The PHP JWT library provides functions for creating JWTs with a variety of options, including custom claims and algorithms for signing and verifying the JWT. It also includes support for common JWT libraries such as JWT-PHP and firebase/php-jwt.  One of the benefits of using JWTs with PHP is that they can be easily integrated into web applications and APIs. They allow for secure communication between the server and client without the need for storing session data on the server, making them ideal for stateless applications.  Overall, PHP JWT is a useful tool for developers looking to implement JWT authentication and authorization in their PHP projects.


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



Implementation Steps
-----------------

- Yii2 installed
- An https enabled site is required for the HttpOnly cookie to work cross-site
- Add JWT parameters in /config/params.php

```php
<?php 
return [
    ...
    'jwt' => [
        'issuer' => 'https://api.torrentgas.com',  //name of your project (for information only)
        'audience' => 'https://frontend.torrentgas.com',  //description of the audience, eg. the website using the authentication (for info only)
        'id' => 'AMqey0yAVrqmhR82RMlWB3zqMpvRP0zaaOheEeq2tmmcEtRYNj',  //a unique identifier for the JWT, typically a random string
        'expire' => '+1 hour',  //the short-lived JWT token is here set to expire after 1 Hours.
        'request_time' => '+1 minute',
        //'request_time' => '+5 seconds',
    ],
    ...
]; 
?>
```
* Add component in configuration in /config/web.php for initializing JWT authentication:

```php
<?php
$config = [
    'components' => [
        ...
        'jwt' => [
            'class' => \kaabar\jwt\Jwt::class,
            'key' => 'SECRET-KEY',  //typically a long random string
        ],
        ...
    ],
];
?>
```

- Add the authenticator behavior to your controllers

- For AuthController.php we must exclude actions that do not require being authenticated, like login, options (when browser sends the 
cross-site OPTIONS request).

```php
<?php
public function behaviors() {
    $behaviors = parent::behaviors();

    $behaviors['authenticator'] = [
        'class' => \kaabar\jwt\JwtHttpBearerAuth::class,
        'except' => [
            'login',
            'options',
        ],
    ];

    return $behaviors;
}
?>
```

- Add the methods generateJwt() and generateRefreshToken() to AuthController.php. We'll be using them in the login/refresh-token actions. Adjust class name for your user model if different.

```php
<?php
private function generateJwt(\app\models\User $user) {
    $jwt = Yii::$app->jwt;
    $signer = $jwt->getSigner('HS256');
    $key = $jwt->getKey();

    //use DateTimeImmutable;
    $now   = new DateTimeImmutable();
    
    $jwtParams = Yii::$app->params['jwt'];

    $token = $jwt->getBuilder()
        // Configures the issuer (iss claim)
        ->issuedBy($jwtParams['issuer'])
        // Configures the audience (aud claim)
        ->permittedFor($jwtParams['audience'])
        // Configures the id (jti claim)
        ->identifiedBy($jwtParams['id'], true)
        // Configures the time that the token was issue (iat claim)
        ->issuedAt($now)
        // Configures the time that the token can be used (nbf claim)
        ->canOnlyBeUsedAfter($now->modify($jwtParams['request_time']))
        // Configures the expiration time of the token (exp claim)
        ->expiresAt($now->modify($jwtParams['expire']))
        // Configures a new claim, called "uid"
        ->withClaim('uid', $user->id)
        // Builds a new token
        ->getToken($signer, $key);

    return $token->toString();
}
?>
```

- Add the login action to AuthController.php:

```php
<?php
public function actionLogin() {
    $model = new \app\models\LoginForm();
    if ($model->load(Yii::$app->request->getBodyParams()) && $model->login()) {
        $user = Yii::$app->user->identity;

        $token = $this->generateJwt($user);
    
        return [
            'user' => $user,
            'token' => (string) $token,
        ];
    } 
    else 
    {
        $model->validate();
        return $model;
    }
}
?>
```
