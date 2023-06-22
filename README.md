Kaabar JWT Auth Extension
=========================
The Yii2 JWT extension is a tool for implementing JWT (JSON Web Token) authentication in Yii2 applications. It allows developers to create APIs that require authentication and authorization, ensuring that only authorized users can access certain resources. The extension provides a simple and flexible way to implement JWT authentication in Yii2, using the JWT library and following the JWT specification. It includes support for creating and verifying JWT tokens, as well as handling token expiration and refresh. The Yii2 JWT extension can be easily integrated into any Yii2 application, making it a powerful tool for API authentication and authorization.


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
- A database table for storing RefreshTokens:

```php
<?php 
    CREATE TABLE `user_refresh_tokens` (
        `user_refresh_tokenID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `urf_userID` INT(10) UNSIGNED NOT NULL,
        `urf_token` VARCHAR(1000) NOT NULL,
        `urf_ip` VARCHAR(50) NOT NULL,
        `urf_user_agent` VARCHAR(1000) NOT NULL,
        `urf_created` DATETIME NOT NULL COMMENT 'UTC',
        PRIMARY KEY (`user_refresh_tokenID`)
    )
    COMMENT='For JWT authentication process';
?>
```

- Add JWT parameters in /config/params.php

```php
<?php 
return [
    ...
    'jwt' => [
        'issuer' => 'https://api.example.com',  //name of your project (for information only)
        'audience' => 'https://example.com',  //description of the audience, eg. the website using the authentication (for info only)
        'id' => 'AMqey0yAVrqmhR82RMlWB3zqMpvRP0zaaOheEeq2tmmcEtRYNj',  //a unique identifier for the JWT, typically a random string
        'expire' => '+1 hour',  //the short-lived JWT token is here set to expire after 1 Hours.
        'request_time' => '+5 seconds', //the time between the two requests. (optional)
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

/**
 * @throws yii\base\Exception
*/
private function generateRefreshToken(\app\models\User $user, \app\models\User $impersonator = null): \app\models\UserRefreshToken {
    $refreshToken = Yii::$app->security->generateRandomString(200);

    // TODO: Don't always regenerate - you could reuse existing one if user already has one with same IP and user agent
    $userRefreshToken = new \app\models\UserRefreshToken([
        'urf_userID' => $user->id,
        'urf_token' => $refreshToken,
        'urf_ip' => Yii::$app->request->userIP,
        'urf_user_agent' => Yii::$app->request->userAgent,
        'urf_created' => gmdate('Y-m-d H:i:s'),
    ]);
    if (!$userRefreshToken->save()) {
        throw new \yii\web\ServerErrorHttpException('Failed to save the refresh token: '. $userRefreshToken->getErrorSummary(true));
    }

    // Send the refresh-token to the user in a HttpOnly cookie that Javascript can never read and that's limited by path
    Yii::$app->response->cookies->add(new \yii\web\Cookie([
        'name' => 'refresh-token',
        'value' => $refreshToken,
        'httpOnly' => true,
        'sameSite' => 'none',
        'secure' => true,
        'path' => '/v1/auth/refresh-token',  //endpoint URI for renewing the JWT token using this refresh-token, or deleting refresh-token
    ]));

    return $userRefreshToken;
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

- Add the refresh-token action to AuthController.php. Call POST /auth/refresh-token when JWT has expired, and call DELETE /auth/refresh-token when user requests a logout (and then delete the JWT token from localStorage).

```php
<?php

public function actionRefreshToken() {

    $refreshToken = Yii::$app->request->cookies->getValue('refresh-token', false);
    if (!$refreshToken) {
        return new \yii\web\UnauthorizedHttpException('No refresh token found.');
    }

    $userRefreshToken = \app\models\UserRefreshToken::findOne(['urf_token' => $refreshToken]);

    if (Yii::$app->request->getMethod() == 'POST') {
        // Getting new JWT after it has expired
        if (!$userRefreshToken) {
            return new \yii\web\UnauthorizedHttpException('The refresh token no longer exists.');
        }

        $user = \app\models\User::find()  //adapt this to your needs
            ->where(['userID' => $userRefreshToken->urf_userID])
            ->andWhere(['not', ['usr_status' => 'inactive']])
            ->one();
        if (!$user) {
            $userRefreshToken->delete();
            return new \yii\web\UnauthorizedHttpException('The user is inactive.');
        }

        $token = $this->generateJwt($user);

        return [
            'status' => 'ok',
            'token' => (string) $token,
        ];

    } elseif (Yii::$app->request->getMethod() == 'DELETE') {
        // Logging out
        if ($userRefreshToken && !$userRefreshToken->delete()) {
            return new \yii\web\ServerErrorHttpException('Failed to delete the refresh token.');
        }

        return ['status' => 'ok'];
    } else {
        return new \yii\web\UnauthorizedHttpException('The user is inactive.');
    }
}
?>
```

- Adapt findIdentityByAccessToken() in your user model to find the authenticated user via the uid claim from the JWT:

```php
<?php

public static function findIdentityByAccessToken($token, $type = null) {
    return static::find()
        ->where(['userID' => (string) $token->getClaim('uid') ])
        ->andWhere(['<>', 'usr_status', 'inactive'])  //adapt this to your needs
        ->one();
}
?>
```

- Also remember to purge all RefreshTokens for the user when the password is changed, eg. in afterSave() in your user model:

```php
<?php

    public function afterSave($isInsert, $changedOldAttributes) {
		// Purge the user tokens when the password is changed
		if (array_key_exists('usr_password', $changedOldAttributes)) {
			\app\models\UserRefreshToken::deleteAll(['urf_userID' => $this->userID]);
		}

		return parent::afterSave($isInsert, $changedOldAttributes);
	}

?>
```

- Make a page where user can delete his RefreshTokens. List the records from user_refresh_tokens that belongs to the given user and allow him to delete the ones he chooses.