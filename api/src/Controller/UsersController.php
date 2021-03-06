<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Event\Event;

/**
 * Users Controller
 *
 * @property \App\Model\Table\UsersTable $Users
 */
class UsersController extends AppController
{

    /**
     * Index method
     *
     * @return \Cake\Network\Response|null
     */
    public $errors=[];


    public function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub

    }

    public function index()
    {
        $this->paginate = [
            'contain' => ['Roles']
        ];
        $users = $this->paginate($this->Users);

        $this->set(compact('users'));
        $this->set('_serialize', ['users']);
    }

    public function beforeRender(Event $event)
    {
        parent::beforeRender($event); // TODO: Change the autogenerated stub
        /* Pagination */

        if ($this->request->param('paging') !== false &&
            in_array($this->response->type(), ['application/json', 'application/xml'])
        ) {
            $this->set('paging', current($this->request->param('paging')));
        }
        if (!array_key_exists('_serialize', $this->viewVars) &&
            in_array($this->response->type(), ['application/json', 'application/xml'])
        ) {
            $this->set('_serialize', true);
        }
        /* Pagination */
    }

    /**
     * View method
     *
     * @param string|null $id User id.
     * @return \Cake\Network\Response|null
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {

        if($id>0){
            $user =$this->paginate(
                $this->Users->find('all',[
                'conditions'=>['Users.id'=>$id]
            ])->contain(['Roles'])
            );

            if($user){
                $this->message='Users found';
                $this->status=1;
                $this->header=200;
                $this->data=array_pop( $user);
            }else{
                $this->message='Users not found';
                $this->status=1;
                $this->header=401;
            }

        }
        else{
            $user =$this->paginate(
                $this->Users->find('all',[
                'order'=>['Users.id'=>'DESC']
            ])->contain(['Roles'])
            );
            if($user){
                $this->message='Users found';
                $this->status=1;
                $this->header=200;
                $this->data=$user;

            }else{
                $this->message='Users not found';
                $this->status=1;
                $this->header=401;
            }

        }




    }

    function _recursion($array) {

        foreach ($array as $key => $value) {

            if (is_array($value)){
                $this->_recursion($value);
            }
            else{
                if($value!='' && !in_array($value,$this->errors))
                    $this->errors[]=$value;
            }

        }
        return $this->errors;
    }

    /**
     * Add method
     *
     * @return \Cake\Network\Response|null Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        if (isset($this->request->data['email'])) {
            $user = $this->Users->newEntity();
            $user = $this->Users->patchEntity($user, $this->request->data);
            $data=$this->Users->save($user);
            if (isset($data->id)) {
                $this->message='User Saved !';
                $this->header=200;
                $this->status=1;
                $this->data=$data;
            }else{
                $this->message='Unable to save at this time !';
                $this->header=403;
                $this->status=1;
                if($user->getErrors()){
                    $this->data=implode("<br>" ,$this->_recursion( $user->getErrors()) );

                }

            }

        }else{
            $this->message='All Fields are required';
            $this->header=404;
            $this->status=1;
            return;
        }

    }

    /**
     * Edit method
     *
     * @param string|null $id User id.
     * @return \Cake\Network\Response|null Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit()
    {

        $id=$this->request->data['id'];
        $user = $this->Users->get($id);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $user = $this->Users->patchEntity($user, $this->request->getData());
            if($user){
                $data=$this->Users->save($user);
                if ($data) {
                    $this->message='Users saved successfully';
                    $this->header=200;
                    $this->status=1;
                    $this->data=$data;
                }else{
                    $this->message='Could not save!';
                    $this->header=403;
                    $this->status=1;
                    if($user->getErrors()){
                        $this->data=implode("<br>" ,$this->_recursion( $user->getErrors()));
                    }

                }
            }else{
                $this->message='invalid request or user';
                $this->header=403;
                $this->status=1;
                return;
            }

        }

    }

    /**
     * Delete method
     *
     * @param string|null $id User id.
     * @return \Cake\Network\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete()
    {
        $this->request->allowMethod(['post', 'delete']);
        if(isset($this->request->data['id'])){
            $id=$this->request->data['id'];
            $user = $this->Users->get($id);
            if ($this->Users->delete($user)) {
                $this->message='User Deleted successfully.';
                $this->status=1;
                $this->header=200;
            } else {
                $this->message='Error ! could not be deleted try again.';
                $this->status=1;
                $this->header=403;
            }
        }else{
            $this->message='User not found.';
            $this->status=1;
            $this->header=404;
        }

    }
}
