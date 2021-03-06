<?php
namespace App\Model;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Common\Helper;
use App\Common\Auth;

final class User extends BaseModel
{
    use SoftDeletes;

    const STATUS_BLOCKED = 0;
    const STATUS_ACTIVE  = 1;
    const STATUS_WAIT    = 2;

    const ROLE_ADMIN     = 1;
    const ROLE_USER      = 2;

    protected $table = 'users';

    protected $fillable = [
        'full_name',
        'email',
        'role_id',
        'status'
    ];

    protected $hidden = [
        'password',
        'access_token',
        'password_reset_token',
    ];

    public static $rules = [
        'create' => [
            'email'    => 'required|email',
            'role_id'  => 'required',
            'password' => 'required',
        ],
        'update' => [
            'email'   => 'required|email',
            'role_id' => 'required',
        ]
    ];

    public function role()
    {
        return $this->hasOne('App\Model\Role', 'id', 'role_id');
    }

    public function scopeCurrentUser($query)
    {
        $user = Auth::getUser();

        if ($user) {
            if ($user->role_id == User::ROLE_ADMIN) {
                return $query;
            }

            $query->where('id', $user->id);
        } else {
            $query->where('id', 0);
        }

        return $query;
    }

    /**
     * @param $email
     *
     * @return bool
     */
    public static function exist($email)
    {
        return User::where('email', $email)->count() > 0;
    }

    /**
     * @param string $email
     *
     * @return User|null
     */
    public static function findUserByEmail($email)
    {
        return User::where('email', $email)->where('status', self::STATUS_ACTIVE)->first();
    }

    /**
     * @param string $accessToken
     *
     * @return User|null
     */
    public static function findUserByAccessToken($accessToken)
    {
        return User::where('access_token', md5($accessToken))->where('status', self::STATUS_ACTIVE)->first();
    }

    /**
     * @param string $resetToken
     *
     * @return User|null
     */
    public static function findByPasswordResetToken($resetToken)
    {
        if (!User::isPasswordResetTokenValid($resetToken)) {
            return null;
        }

        return User::where('password_reset_token', $resetToken)->where('status', self::STATUS_ACTIVE)->first();
    }

    /**
     * @param string $token
     *
     * @return bool
     */
    public static function isPasswordResetTokenValid($token)
    {
        if (empty($token)) {
            return false;
        }

        $timestamp = (int) substr($token, strrpos($token, '_') + 1);
        // TODO Вынести expire в конфиг
        $expire = 3600;
        return $timestamp + $expire >= time();
    }

    /**
     * @void
     */
    public function generatePasswordResetToken()
    {
        $this->password_reset_token = Helper::generateRandomString() . '_' . time();
    }

    /**
     * @void
     */
    public function removePasswordResetToken()
    {
        $this->password_reset_token = null;
    }

    /**
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->access_token = null;
        $this->password = password_hash($password, PASSWORD_DEFAULT, ['cost' => 13]);
    }
}