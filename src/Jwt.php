<?php
namespace kaabar\jwt;

use DateTimeImmutable;
use Lcobucci\Clock\Clock;
use Lcobucci\Clock\FrozenClock;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;
use Lcobucci\JWT\Validation\Constraint\IdentifiedBy;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\RelatedTo;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\ValidAt;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Lcobucci\JWT\Validation\Validator;
use Yii;
use yii\base\Component;
use yii\base\InvalidArgumentException;

/**
 * JSON Web Token implementation, based on this library:
 * https://github.com/lcobucci/jwt
 *
 * @author Chetan Patel <cpjeslot@gmail.com>
 * @since 1.0.0
 */
class Jwt extends Component
{
    private Clock $clock;
    /**
     * @var array Supported algorithms
     */
    public $supportedAlgs = [
        'HS256' => \Lcobucci\JWT\Signer\Hmac\Sha256::class,
        'HS384' => \Lcobucci\JWT\Signer\Hmac\Sha384::class,
        'HS512' => \Lcobucci\JWT\Signer\Hmac\Sha512::class,
        'ES256' => \Lcobucci\JWT\Signer\Ecdsa\Sha256::class,
        'ES384' => \Lcobucci\JWT\Signer\Ecdsa\Sha384::class,
        'ES512' => \Lcobucci\JWT\Signer\Ecdsa\Sha512::class,
        'RS256' => \Lcobucci\JWT\Signer\Rsa\Sha256::class,
        'RS384' => \Lcobucci\JWT\Signer\Rsa\Sha384::class,
        'RS512' => \Lcobucci\JWT\Signer\Rsa\Sha512::class,
    ];

    /**
     * @var Key|string $key The key
     */
    public $key;
	
	/**
    * @var string|array|callable Parser
    **/
    public $getParser;
    
    /**
     * @see [[Lcobucci\JWT\Builder::__construct()]]
     * @param Encoder|null $encoder
     * @param ClaimFactory|null $claimFactory
     * @return Builder
     */
    public function getBuilder()
    {
        return (new Builder(new JoseEncoder(), ChainedFormatter::default()));
    }

    /**
     * @param string $alg
     * @return Signer
     */
    public function getSigner($alg)
    {
        $class = $this->supportedAlgs[$alg];

        return new $class();
    }

    /**
     * @param strng $content
     * @param string|null $passphrase
     * @return Key
     */
    public function getKey($content = null, $passphrase = null)
    {
        $content = $content ?: $this->key;
        return InMemory::plainText($content);
    }

    // /**
    //  * Parses the JWT and returns a token class
    //  * @param string $token JWT
    //  * @param bool $validate
    //  * @param bool $verify
    //  * @return Token|null
    //  * @throws \Throwable
    //  */
    public function loadToken($token, $validate = true, $verify = true)
    {
        try {
            
            $this->getParser = new Parser(new JoseEncoder());
            $token = $this->getParser->parse((string) $token);

        } catch (CannotDecodeContent | InvalidTokenStructure | UnsupportedHeaderFound $e) {
            Yii::$app->response->statusCode = 422;
            return $e->getMessage();
        }
        
        if ($validate && !$this->validateToken($token)) {
            return null;
        }

        if ($verify && !$this->verifyToken($token)) {
            return null;
        }
        
        return $token;
    }

    // /**
    //  * Validate token
    //  * @param $token token object
    //  * @param int|null $currentTime
    //  * @return bool
    //  */
    public function validateToken($token, $currentTime = null)
    {
        $validator = new Validator();
            
        $jwtParams = Yii::$app->params['jwt'];
        
        $clock = new FrozenClock(new DateTimeImmutable());
        
        try {
            $validator->assert($token, new IssuedBy($jwtParams['issuer']));
            $validator->assert($token, new PermittedFor($jwtParams['audience']));
            $validator->assert($token, new identifiedBy($jwtParams['id']));
            $validator->assert($token, new SignedWith(new $this->supportedAlgs['HS256'](), InMemory::plainText($this->key)));
            $validator->assert($token, new ValidAt($clock));
            
        } catch (RequiredConstraintsViolated $e) {
            return false;
        }

        return true;
    }

    /**
     * Validate token
     * @param $token token object
     * @return bool
     * @throws \Throwable
     */
    public function verifyToken($token)
    {
        $alg = $token->Headers()->get('alg');

        if (empty($this->supportedAlgs[$alg])) {
            throw new InvalidArgumentException('Algorithm not supported');
        }
        
        return true;
    }
}