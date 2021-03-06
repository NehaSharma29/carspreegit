<?php
/**
 * Created by PhpStorm.
 * User: webwerks
 * Date: 2/5/17
 * Time: 5:02 PM
 */

namespace App\Controller;

use App\Controller\AppController;
use Cake\Core\Configure;
use Cake\Network\Exception\InternalErrorException;
use Cake\Network\Exception\UnauthorizedException;
use Cake\Event\Event;
use Cake\Utility\Text;
use Cake\Utility\Security;


class LoginController extends AppController{

    public $sendData=[];
    public function initialize(){
        parent::initialize(); // TODO: Change the autogenerated stub
        $this->loadModel('Users');
    }

    public function beforeFilter(Event $event)
    {
        $this->Auth->allow(['index','logout']);
    }

    /**
     * Index login method API /api/login method:POST
     * @return json response
     */

    public function index()
    {
        try {
            if (!isset($this->request->data['email'])) {
                $this->header = 403;
                $this->message = 'Username/email is required';
                $this->status = 1;
                $this->responseData = [];
            } else if (!isset($this->request->data['password'])) {
                $this->header = 403;
                $this->message = 'Password is required';
                $this->status = 1;
                $this->responseData = [];
            } else{

                if(isset($this->request->data['username'])){
                    $this->Auth->config('authenticate',[
                        'Form' => [
                            'fields' => ['username' => 'username', 'password' => 'password'],
                            'userModel'=>'Users',
                        ],
                    ]);
                }

                $user = $this->Auth->identify();
            if (!$user) {
                $this->header = 401;
                $this->message = 'incorrect email/username or password';
                $this->status = 1;
                $this->responseData = [];
            } else {
                //if everything is ok then set userdata to session
                //Generate user auth token
                $this->token = Security::hash($user['id'] . $user['email'].time(), 'sha1', true);
                /* save user token */
                $this->loadModel('LoginHistories');
                $this->LoginHistories->saveHistory([
                    'user_id'=>$user['id'],
                    'token'=>$this->token,
                    'signin'=>date('H:i:s'),
                    'signout'=>date('H:i:s'),
                    'ip'=>$_SERVER['REMOTE_ADDR'],
                    'created'=>date('Y-m-d H:i:s'),
                    'modified'=>date('Y-m-d H:i:s'),
                    'deleted'=>0,
                    'device_id'=>$_SERVER['HTTP_USER_AGENT'],
                    'device_type'=>$_SERVER['HTTP_USER_AGENT']
                ]);
                $this->header = 200;
                $this->message = 'Login Successful';
                $this->status = 1;
                $this->data = $user + ['request_session' => $this->token];
                //Add token to Auth session
                $this->request->session()->write('Auth.User.token', $this->token);
                //return auth token
            }

        }
        }catch (UnauthorizedException $e){
            $this->header=500;
            $this->message=$e->getMessage();
            $this->status=1;
            $this->responseData=[];
        }
    }

    /**
     * Logout user
     * api url: /api/login DELETE method
     * @return json response
     */

    public function logout(){
        $this->Auth->logout();
        $this->request->session()->delete('Auth.User.token');
        $this->message='Logged out successfully.';
        $this->header=200;
        $this->status=1;
    }
}