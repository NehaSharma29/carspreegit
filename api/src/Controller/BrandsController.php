<?php
namespace App\Controller\Admin;

use Cake\Core\Configure;

class BrandsController extends AdminAppController {
	/**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('Security');`
     *
     * @return void
     */
    public function initialize() {
        parent::initialize();
        
        $this->loadComponent('Cookie');
        $this->loadComponent('RequestHandler');
		$this->loadComponent('Search.Prg', [
			// This is default config. You can modify "actions" as needed to make
			// the PRG component work only for specified methods.
			'actions' => ['index', 'lookup']
		]);
    }//end initialize()

	/**
	 * beforeFilter function
	 * 
	 * @param $event
	 * 
	 * return void
	 **/
    public function beforeFilter(\Cake\Event\Event $event){
        parent::beforeFilter($event);
    }//end beforeFilter()

	/**
	 * Function for show listing brands
	 * 
	 * @param null
	 * 
	 * return void
	 **/
    public function index() {
        $this->loadModel('Brands');
        
		$query = $this->Brands->find('all', ['search' => $this->request->query,'conditions' => ['is_deleted' => 0]]);
        
        $result = $this->paginate($query);
        $this->set('result', $query);
    }//end index()
	
	/**
	 * Function for add brand
	 * 
	 * @param null
	 * 
	 * return void
	 **/
    public function add(){
        $this->loadModel('Brands');
        $Brands = $this->Brands->newEntity();
        
        if ($this->request->is('post')){
			$this->request->data['status'] = 1;
			$this->request->data['is_deleted'] = 0;
            $Brands = $this->Brands->patchEntity($Brands, $this->request->data);
            /* Create User  */
            if ($this->Brands->save($Brands)) {
                $this->Flash->success(__('The brand has been saved.'));
                return $this->redirect(['action' => 'index']);
            }else{
				$this->Flash->error(__('The brand could not be saved. Please, try again.'));
			}
        }
        $this->set(compact('Brands'));
    }//end add()
    
    
    /**
	 * Function for edit brand
	 * 
	 * @param $id as brand id
	 * 
	 * return void
	 **/
    public function edit($id = null){
        $this->loadModel('Brands');
        
        $Brands = $this->Brands->get($id);
        
        if ($this->request->is(['patch', 'post', 'put'])) {
            $Brands = $this->Brands->patchEntity($Brands, $this->request->data);
            if ($this->Brands->save($Brands)) {
                $this->Flash->success(__('The brand has been updated.'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The brand could not be saved. Please, try again.'));
        }
        
        $this->set(compact('Brands'));
    }//end edit()
    
    /**
	 * Function for remove brand
	 * 
	 * @param $id as brand id
	 * 
	 * return void
	 **/
    public function delete($id = null){
        $this->loadModel('Brands');
        $Brands = $this->Brands->get($id);
        $Brands->is_deleted = 1;
		if($this->Brands->save($Brands)){
			$this->Flash->success(__('The brand has been deleted.'));
		}else{
			$this->Flash->error(__('The brand could not be saved. Please, try again.'));
		}
		return $this->redirect(['action' => 'index']);
    }//end delete()
    
    /**
	 * Function for show listing models
	 * 
	 * @param null
	 * 
	 * return void
	 **/
    public function models() {
        $this->loadModel('Models');
        $con 	= [];
		
        $recordPerPageLimit = Configure::read('SiteSettingsTbl.per_page_limit');
        $recordPerPageLimit = !empty($recordPerPageLimit) ? $recordPerPageLimit : 5;
        $record_per_page = (isset($this->request->query['limit'])) ? $this->request->query['limit'] : $recordPerPageLimit;
        
		$query = $this->Models->find('all', ['search' => $this->request->query,'conditions' => ['Models.is_deleted' => 0],'contain' => 'Brands']);
        
        $result = $query;
        $this->set('result', $result);
    }//end models()
    
    /**
	 * Function for add model
	 * 
	 * @param null
	 * 
	 * return void
	 **/
    public function addModel(){
        $this->loadModel('Brands');
        $this->loadModel('Models');
        $Brands = $this->Brands->find('list',['conditions'=> ['is_deleted' => 0]])->toArray();
        $Models = $this->Models->newEntity();
        
        if ($this->request->is('post')){
			$this->request->data['status'] = 1;
			$this->request->data['is_deleted'] = 0;
			
            $Models = $this->Models->patchEntity($Models, $this->request->data);
            /* Create User  */
            if ($this->Models->save($Models)) {
                $this->Flash->success(__('The model has been saved.'));
                return $this->redirect(['action' => 'models']);
            }else{
				$this->Flash->error(__('The model could not be saved. Please, try again.'));
			}
        }else{
			$this->request->data = $Models;
		}
        $this->set(compact('Models','Brands'));
    }//end addModel()
    
    
    /**
	 * Function for edit model
	 * 
	 * @param $id as model id
	 * 
	 * return void
	 **/
    public function editModel($id = null){
        $this->loadModel('Brands');
        $this->loadModel('Models');
        $Brands = $this->Brands->find('list',['conditions'=> ['is_deleted' => 0]])->toArray();
        $Models = $this->Models->get($id);
        
        if ($this->request->is(['patch', 'post', 'put'])) {
            $Models = $this->Models->patchEntity($Models, $this->request->data);
            if ($this->Models->save($Models)){
                $this->Flash->success(__('The model has been updated.'));
                return $this->redirect(['action' => 'models']);
            }else{
				$this->Flash->error(__('The model could not be saved. Please, try again.'));
			}
        }else{
			$this->request->data = $Models;
		}
        
        $this->set(compact('Brands','Models'));
    }//end editModel()
    
    /**
	 * Function for remove model
	 * 
	 * @param $id as model id
	 * 
	 * return void
	 **/
    public function deleteModel($id = null){
        $this->loadModel('Models');
        $Models = $this->Models->get($id);
        $Models->is_deleted = 1;
		if($this->Models->save($Models)){
			$this->Flash->success(__('The model has been deleted.'));
		}else{
			$this->Flash->error(__('The model could not be saved. Please, try again.'));
		}
		return $this->redirect(['action' => 'models']);
    }//end deleteModel()
    
    /**
	 * Function for show listing models
	 * 
	 * @param null
	 * 
	 * return void
	 **/
    public function logos(){
        $this->loadModel('BrandLogos');
        $con 	= [];
		
        $recordPerPageLimit = Configure::read('SiteSettingsTbl.per_page_limit');
        $recordPerPageLimit = !empty($recordPerPageLimit) ? $recordPerPageLimit : 5;
        $record_per_page = (isset($this->request->query['limit'])) ? $this->request->query['limit'] : $recordPerPageLimit;
        
		$query = $this->BrandLogos->find('all', ['search' => $this->request->query,'conditions' => ['BrandLogos.is_deleted' => 0],'contain' => 'Brands']);
        
        $result = $query;
        $this->set('result', $result);
    }//end logos()
    
    /**
	 * Function for add logo
	 * 
	 * @param null
	 * 
	 * return void
	 **/
    public function addLogo(){
        $this->loadModel('BrandLogos');
        $this->loadModel('Brands');
        $Brands = $this->Brands->find('list',['conditions'=> ['is_deleted' => 0]])->toArray();
        $BrandLogos = $this->BrandLogos->newEntity();
        
        if ($this->request->is('post')){
			if(!empty($this->request->data['logo']['name'])){
				$imageName = time().'_'.$this->request->data['logo']['name'];
				$fileExt 	= pathinfo($imageName, PATHINFO_EXTENSION);
				$imgExtensions = Configure::read('Site.image_extensions');
				$tmpName = $this->request->data['logo']['tmp_name'];
				if(in_array($fileExt,$imgExtensions)){
					$logoFolder = WWW_ROOT.'uploads'.DS.'BrandLogo';
					if (!file_exists($logoFolder)) {
						mkdir($logoFolder);
					}
					move_uploaded_file($tmpName, "$logoFolder/$imageName");
					$this->request->data['logo'] = $imageName;
				}else{
					$this->request->data['is_active'] = 1;
					$this->request->data['is_deleted'] = 0;
					$BrandLogos = $this->BrandLogos->patchEntity($BrandLogos, $this->request->data);
				}
			}
			$this->request->data['is_active'] = 1;
			$this->request->data['is_deleted'] = 0;
			
            $BrandLogos = $this->BrandLogos->patchEntity($BrandLogos, $this->request->data);
            /* Create User  */
            if ($this->BrandLogos->save($BrandLogos)) {
                $this->Flash->success(__('The logo has been saved.'));
                return $this->redirect(['action' => 'logos']);
            }else{
				$this->Flash->error(__('The logo could not be saved. Please, try again.'));
			}
        }else{
			$this->request->data = $BrandLogos;
		}
        $this->set(compact('BrandLogos','Brands'));
    }//end addLogo()
    
    
    /**
	 * Function for edit logo
	 * 
	 * @param $id as logo id
	 * 
	 * return void
	 **/
    public function editLogo($id = null){
        $this->loadModel('BrandLogos');
        $this->loadModel('Brands');
        $Brands = $this->Brands->find('list',['conditions'=> ['is_deleted' => 0]])->toArray();
        $BrandLogos = $this->BrandLogos->get($id);
        if ($this->request->is(['patch', 'post', 'put'])) {
			if(!empty($this->request->data['logo']['name'])){
				$imageName = time().'_'.$this->request->data['logo']['name'];
				$fileExt 	= pathinfo($imageName, PATHINFO_EXTENSION);
				$imgExtensions = Configure::read('Site.image_extensions');
				
				$tmpName = $this->request->data['logo']['tmp_name'];
				if(in_array($fileExt,$imgExtensions)){
					$logoFolder = WWW_ROOT.'uploads'.DS.'BrandLogo';
					if (!file_exists($logoFolder)) {
						mkdir($logoFolder);
					}
					$image 		= $BrandLogos->logo;
					$logoFolder = WWW_ROOT.'uploads'.DS.'BrandLogo'.DS;
					if(file_exists($logoFolder.$image)){
						@unlink($logoFolder.$image);
					}
					move_uploaded_file($tmpName, "$logoFolder/$imageName");
					$this->request->data['logo'] = $imageName;
				}else{
					$BrandLogos = $this->BrandLogos->patchEntity($BrandLogos, $this->request->data);
				}
			}else{
				$this->request->data['logo'] = $BrandLogos->logo;
			}
            $BrandLogos = $this->BrandLogos->patchEntity($BrandLogos, $this->request->data);
            if ($this->BrandLogos->save($BrandLogos)){
                $this->Flash->success(__('The logo has been updated.'));
                return $this->redirect(['action' => 'logos']);
            }else{
				$this->Flash->error(__('The logo could not be saved. Please, try again.'));
			}
        }else{
			$this->request->data = $BrandLogos;
		}
        
        $this->set(compact('BrandLogos','Brands'));
    }//end editLogo()
    
    /**
	 * Function for remove logo
	 * 
	 * @param $id as logo id
	 * 
	 * return void
	 **/
    public function deleteLogo($id = null){
        $this->loadModel('BrandLogos');
        $BrandLogos = $this->BrandLogos->get($id);
        $BrandLogos->is_deleted = 1;
		if($this->BrandLogos->save($BrandLogos)){
			$image 		= $BrandLogos->logo;
			$logoFolder = WWW_ROOT.'uploads'.DS.'BrandLogo'.DS;
			if(file_exists($logoFolder.$image)){
				@unlink($logoFolder.$image);
			}
			$this->Flash->success(__('The logo has been deleted.'));
		}else{
			$this->Flash->error(__('The logo could not be saved. Please, try again.'));
		}
		return $this->redirect(['action' => 'logos']);
    }//end deleteLogo()
    
    /**
	 * Function for show and update model years
	 * 
	 * @param null
	 * 
	 * return void
	 **/
    public function modelYear(){
        $this->loadModel('ModelYears');
        $this->loadModel('Brands');
		$ModelYears = $this->ModelYears->newEntity();
        $ModelYearLists = $this->ModelYears->find('list',['conditions'=> ['is_deleted' => 0]])->toArray();
        if ($this->request->is('post')){
			if(!empty($this->request->data['year'])){
				$this->request->data['status'] 	= 1;
				$this->request->data['is_deleted'] 	= 0;
				$flag	=	false;
				foreach($this->request->data['year'] as $year){
					if(!in_array($year,$ModelYearLists)){
						$ModelYearFirst = $this->ModelYears->find('all',['conditions'=> ['year' => $year]])->toArray();
						if(!empty($ModelYearFirst)){
							$id  = $ModelYearFirst[0]->id;
							$ModelYears = $this->ModelYears->get($id);
							$ModelYears->is_deleted = 0;
							if($this->ModelYears->save($ModelYears)){
								$flag = true;
							}
						}else{
							$this->request->data['year'] = $year;
							$ModelYears = $this->ModelYears->newEntity();
							$ModelYears = $this->ModelYears->patchEntity($ModelYears,$this->request->data);
							if ($this->ModelYears->save($ModelYears)){
								$flag = true;
							}
						}
					}else{
						$result = array_diff($ModelYearLists,$this->request->data['year']);
						if(!empty($result)){
							foreach($result as $key => $id){
								$ModelYears = $this->ModelYears->get($key);
								$ModelYears->is_deleted = 1;
								if($this->ModelYears->save($ModelYears)){
									$flag = true;
								}
							}
						}
						$flag = true;
					}
				}
			}            
            /* Create model year */
            if ($flag){
                $this->Flash->success(__('The model year has been saved.'));
                return $this->redirect(['action' => 'model_year']);
            }else{
				$this->Flash->error(__('The model year could not be saved. Please, try again.'));
			}
        }else{
			$this->request->data = $ModelYears;
		}
        $this->set(compact('ModelYears','ModelYearLists'));
    }//end modelYear()
    
    /**
	 * Function for show and update exterior colors
	 * 
	 * @param null
	 * 
	 * return void
	 **/
    public function exteriorColor(){
        $this->loadModel('ExteriorColors');
		$ExteriorColors = $this->ExteriorColors->newEntity();
        $ExteriorColorLists = $this->ExteriorColors->find('list',['conditions'=> ['is_deleted' => 0]])->toArray();
        if ($this->request->is('post')){
			if(!empty($this->request->data['name'])){
				$this->request->data['status'] 	= 1;
				$this->request->data['is_deleted'] 	= 0;
				$flag	=	false;
				foreach($this->request->data['name'] as $year){
					if(!in_array($year,$ExteriorColorLists)){
						$ModelYearFirst = $this->ExteriorColors->find('all',['conditions'=> ['name' => $year]])->toArray();
						if(!empty($ModelYearFirst)){
							$id  = $ModelYearFirst[0]->id;
							$ExteriorColors = $this->ExteriorColors->get($id);
							$ExteriorColors->is_deleted = 0;
							if($this->ExteriorColors->save($ExteriorColors)){
								$flag = true;
							}
						}else{
							$this->request->data['name'] = $year;
							$ExteriorColors = $this->ExteriorColors->newEntity();
							$ExteriorColors = $this->ExteriorColors->patchEntity($ExteriorColors,$this->request->data);
							if ($this->ExteriorColors->save($ExteriorColors)){
								$flag = true;
							}
						}
					}else{
						$result = array_diff($ExteriorColorLists,$this->request->data['name']);
						if(!empty($result)){
							foreach($result as $key => $id){
								$ExteriorColors = $this->ExteriorColors->get($key);
								$ExteriorColors->is_deleted = 1;
								if($this->ExteriorColors->save($ExteriorColors)){
									$flag = true;
								}
							}
						}
						$flag = true;
					}
				}
			}            
            /* Create model year */
            if ($flag){
                $this->Flash->success(__('The exterior color has been saved.'));
                return $this->redirect(['action' => 'exterior_color']);
            }else{
				$this->Flash->error(__('The exterior color could not be saved. Please, try again.'));
			}
        }else{
			$this->request->data = $ExteriorColors;
		}
        $this->set(compact('ExteriorColors','ExteriorColorLists'));
    }//end exteriorColor()
    
    /**
	 * Function for show and update interior colors
	 * 
	 * @param null
	 * 
	 * return void
	 **/
    public function interiorColor(){
        $this->loadModel('InteriorColors');
		$InteriorColors = $this->InteriorColors->newEntity();
        $InteriorColorLists = $this->InteriorColors->find('list',['conditions'=> ['is_deleted' => 0]])->toArray();
        if ($this->request->is('post')){
			if(!empty($this->request->data['name'])){
				$this->request->data['status'] 	= 1;
				$this->request->data['is_deleted'] 	= 0;
				$flag	=	false;
				foreach($this->request->data['name'] as $year){
					if(!in_array($year,$InteriorColorLists)){
						$ModelYearFirst = $this->InteriorColors->find('all',['conditions'=> ['name' => $year]])->toArray();
						if(!empty($ModelYearFirst)){
							$id  = $ModelYearFirst[0]->id;
							$InteriorColors = $this->InteriorColors->get($id);
							$InteriorColors->is_deleted = 0;
							if($this->InteriorColors->save($InteriorColors)){
								$flag = true;
							}
						}else{
							$this->request->data['name'] = $year;
							$InteriorColors = $this->InteriorColors->newEntity();
							$InteriorColors = $this->InteriorColors->patchEntity($InteriorColors,$this->request->data);
							if ($this->InteriorColors->save($InteriorColors)){
								$flag = true;
							}
						}
					}else{
						$result = array_diff($InteriorColorLists,$this->request->data['name']);
						if(!empty($result)){
							foreach($result as $key => $id){
								$InteriorColors = $this->InteriorColors->get($key);
								$InteriorColors->is_deleted = 1;
								if($this->InteriorColors->save($InteriorColors)){
									$flag = true;
								}
							}
						}
						$flag = true;
					}
				}
			}            
            /* Create model year */
            if ($flag){
                $this->Flash->success(__('The interior color has been saved.'));
                return $this->redirect(['action' => 'interior_color']);
            }else{
				$this->Flash->error(__('The interior color could not be saved. Please, try again.'));
			}
        }else{
			$this->request->data = $InteriorColors;
		}
        $this->set(compact('InteriorColors','InteriorColorLists'));
    }//end exteriorColor()
    
    /**
	 * Function for show listing of purchase types
	 * 
	 * @param null
	 * 
	 * return void
	 **/
    public function purchaseTypes(){
        $this->loadModel('PurchaseTypes');
        
		$query = $this->PurchaseTypes->find('all', ['search' => $this->request->query,'conditions' => ['PurchaseTypes.is_deleted' => 0]]);
        
        $result = $query;
        $this->set('result', $result);
    }//end logos()
    
    /**
	 * Function for add purchase type
	 * 
	 * @param null
	 * 
	 * return void
	 **/
    public function addPurchaseType(){
        $this->loadModel('PurchaseTypes');
        $PurchaseTypes = $this->PurchaseTypes->newEntity();
        
        if ($this->request->is('post')){
			$this->request->data['status'] = 1;
			$this->request->data['is_deleted'] = 0;
            $PurchaseTypes = $this->PurchaseTypes->patchEntity($PurchaseTypes, $this->request->data);
            /* Create User  */
            if ($this->PurchaseTypes->save($PurchaseTypes)) {
                $this->Flash->success(__('The purchase type has been saved.'));
                return $this->redirect(['action' => 'purchaseTypes']);
            }else{
				$this->Flash->error(__('The purchase type could not be saved. Please, try again.'));
			}
        }
        $this->set(compact('PurchaseTypes'));
    }//end addPurchaseType()
    
    
    /**
	 * Function for edit purchase type
	 * 
	 * @param $id as purchase type id
	 * 
	 * return void
	 **/
    public function editPurchaseType($id = null){
        $this->loadModel('PurchaseTypes');
        
        $PurchaseTypes = $this->PurchaseTypes->get($id);
        
        if ($this->request->is(['patch', 'post', 'put'])) {
            $PurchaseTypes = $this->PurchaseTypes->patchEntity($PurchaseTypes, $this->request->data);
            if ($this->PurchaseTypes->save($PurchaseTypes)) {
                $this->Flash->success(__('The purchase type has been updated.'));
                return $this->redirect(['action' => 'purchaseTypes']);
            }
            $this->Flash->error(__('The purchase type could not be saved. Please, try again.'));
        }
        
        $this->set(compact('PurchaseTypes'));
    }//end editPurchaseType()
    
    /**
	 * Function for remove purchase type
	 * 
	 * @param $id as purchase type id
	 * 
	 * return void
	 **/
    public function deletePurchaseType($id = null){
        $this->loadModel('PurchaseTypes');
        $PurchaseTypes = $this->PurchaseTypes->get($id);
        $PurchaseTypes->is_deleted = 1;
		if($this->PurchaseTypes->save($PurchaseTypes)){
			$this->Flash->success(__('The purchase type has been deleted.'));
		}else{
			$this->Flash->error(__('The purchase type could not be saved. Please, try again.'));
		}
		return $this->redirect(['action' => 'purchaseTypes']);
    }//end deletePurchaseType()
    
    /**
	 * Function for show listing of month time durations
	 * 
	 * @param null
	 * 
	 * return void
	 **/
    public function months(){
        $this->loadModel('Months');
        
		$query = $this->Months->find('all', ['search' => $this->request->query,'conditions' => ['Months.is_deleted' => 0]]);
        
        $result = $query;
        $this->set('result', $result);
    }//end logos()
    
    /**
	 * Function for add Month
	 * 
	 * @param null
	 * 
	 * return void
	 **/
    public function addMonth(){
        $this->loadModel('Months');
        $Months = $this->Months->newEntity();
        
        if ($this->request->is('post')){
			$this->request->data['status'] = 1;
			$this->request->data['is_deleted'] = 0;
            $Months = $this->Months->patchEntity($Months, $this->request->data);
            /* Create User  */
            if ($this->Months->save($Months)) {
                $this->Flash->success(__('The month duration has been saved.'));
                return $this->redirect(['action' => 'months']);
            }else{
				$this->Flash->error(__('The month duration could not be saved. Please, try again.'));
			}
        }
        $this->set(compact('Months'));
    }//end addPurchaseType()
    
    
    /**
	 * Function for edit month
	 * 
	 * @param $id as month id
	 * 
	 * return void
	 **/
    public function editMonth($id = null){
        $this->loadModel('Months');
        
        $Months = $this->Months->get($id);
        
        if ($this->request->is(['patch', 'post', 'put'])) {
            $Months = $this->Months->patchEntity($Months, $this->request->data);
            if ($this->Months->save($Months)) {
                $this->Flash->success(__('The month duration has been updated.'));
                return $this->redirect(['action' => 'months']);
            }
            $this->Flash->error(__('The month duration could not be saved. Please, try again.'));
        }
        
        $this->set(compact('Months'));
    }//end editMonth()
    
    /**
	 * Function for remove month
	 * 
	 * @param $id as month id
	 * 
	 * return void
	 **/
    public function deleteMonth($id = null){
        $this->loadModel('Months');
        $Months = $this->Months->get($id);
        $Months->is_deleted = 1;
		if($this->Months->save($Months)){
			$this->Flash->success(__('The month duration has been deleted.'));
		}else{
			$this->Flash->error(__('The month duration could not be saved. Please, try again.'));
		}
		return $this->redirect(['action' => 'months']);
    }//end deleteMonth()
}//end class
