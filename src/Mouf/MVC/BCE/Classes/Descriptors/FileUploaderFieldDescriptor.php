<?php
namespace Mouf\MVC\BCE\Classes\Descriptors;

use Mouf\Html\Widgets\FileUploaderWidget\SimpleFileUploaderWidget;

use Mouf\MVC\BCE\BCEForm;
use Mouf\MoufManager;

/**
 * This class is the simpliest FieldDescriptor:
 * it handles a field that has no "connections" to other objects (
 * as user name or login for example)
 * @Component
 */
class FileUploaderFieldDescriptor extends FieldDescriptor {

	/**
	 * The name of the function that retruns the value of the field from the bean.
	 * For example, with $user->getLogin(), the $getter property should be "getLogin"
	 * @Property
	 * @var string
	 */
	public $getter;
	
	/**
	 * The name of the function that sets the value of the field into the bean.
	 * For example, with $user->setLogin($login), the $setter property should be "setLogin"
	 * @Property
	 * @var string
	 */
	public $setter;

	/**
	 * The value of the field once the FiedDescriptor has been loaded
	 * @var mixed
	 */
	public $value;

	/**
	 * Folder where the file will be saved. "resources" by default
	 * @var string
	 */
	public $folder = 'resources';

    /**
     * Whether we should set in the bean the relative path to $folder or the relative path to ROOT_PATH
     * @var bool
     */
    public $saveAbsolutePath = true;

	/**
	 * 
	 * 
	 * @Property
	 * @var SimpleFileUploaderWidget
	 */
	public $fileUploaderWidget;
	

	/**
	 * Loads the values of the bean into the descriptors, calling main bean's getter
	 * Eventually formats the value before displaying it 	
	 * @param mixed $mainBean
	 */
	public function load($mainBean, $id = null, &$form = null) {
		$fieldValue = $this->getValue($mainBean);
		$descriptorInstance = new FieldDescriptorInstance($this, $form, $id);

		/* @var $fileUploaderLibrary WebLibraryManager */
		$moufManager = MoufManager::getMoufManager();
		$fileUploaderLibrary = $moufManager->getInstance('fileUploaderLibrary');
		$form->getWeblibraryManager()->addLibrary($fileUploaderLibrary);
		
		$descriptorInstance->value = $fieldValue;
		return $descriptorInstance;
	}
	

	/**
	 * (non-PHPdoc)
	 * For a FieldDecsriptor instance, the preSave function id responsible for :
	 *  - unformatting the posted value
	 *  - valideting the value
	 *  - setting the value into the bean (case of BaseFieldDescriptors)
	 *  - settings the linked ids to associate in mapping table (Many2ManyFieldDEscriptors)
	 * @see BCEFieldDescriptorInterface::preSave()
	 */
	public function preSave($post, BCEForm &$form, $bean, $isNew) {
		// If the first time, force the value to empty because not nullable field will cause an error
		if($isNew) {
			// TODO optimization, detect if the field is nullable
			$this->setValue($bean, '');
		}
		// Retrieve post to check if the user delete a file
		// Not the first time because no file to remove
		else {
			if($post != null) {
				$value = isset($post['remove-file-upload-'.$this->getFieldName()]) ? $post[$this->getFieldName()] : null;
			} else {
				$value = get('remove-file-upload-'.$this->getFieldName());
			}
			if($value && $this->getValue($bean) == $value) {
				unlink(ROOT_PATH.$value);
				// TODO optimization, detect if the field is nullable
				$this->setValue($bean, '');
			}
		}
	}
	
	
	/**
	 * (non-PHPdoc)
	 * @see BCEFieldDescriptorInterface::postSave()
	 */
	public function postSave($bean, $beanId, $postValues) {
		if($this->fileUploaderWidget->hasFileToMove($this->getFieldName())) {
			//TODO recupere le nom du bean !!
            if(substr($this->folder, -1) == "/"){
                $folder = $this->folder.$beanId;
            }else{
                $folder = $this->folder.'/'.$beanId;
            }

			if(is_dir(ROOT_PATH.$folder)) {
				if ($dh = opendir(ROOT_PATH.$folder)) {
					while ($file = readdir($dh)) {
						if (is_file(ROOT_PATH.$folder."/".$file)) {
							unlink(ROOT_PATH.$folder."/".$file);
						}
					}
				}
			}
			
			$fileList = $this->fileUploaderWidget->moveFile($this->getFieldName(), ROOT_PATH.$folder);
			
			$value = array_shift($fileList);
		
			//Set value context before saving
            if($this->saveAbsolutePath){
                $this->setValue($bean, $folder.'/'.$value);
            }else{
                $this->setValue($bean, $value);
            }

			$bean->save();
		}
		return;
	} 
	
	/**
	 * Simply calls the setter of the descriptor's related field into the bean
	 * @param mixed $mainBean
	 * @param mixed $value
	 */
	public function setValue($mainBean, $value) {
		call_user_func(array($mainBean, $this->setter), $value);
	}
	
	public function getValue($mainBean){
		if ($mainBean == null){
			$fieldValue = null;
		}else{
			$fieldValue = call_user_func(array($mainBean, $this->getter));
		}
		return $fieldValue;
	}
	

	/**
	 * Returns the bean's value after loading the descriptor
	 */
	public function getFieldValue() {
		return $this->value;
	}

	/**
	 * Returns the label of the field
	 */
	public function getFieldLabel() {
		return $this->label;
	}

	/**
	 * Returns the instance of FileUploaderWidget
	 */
	public function getFileUploaderWidget() {
		return $this->fileUploaderWidget;
	}

}