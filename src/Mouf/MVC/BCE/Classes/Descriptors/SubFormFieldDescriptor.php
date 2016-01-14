<?php
namespace Mouf\MVC\BCE\Classes\Descriptors;

use Mouf\Html\Widgets\Form\Styles\LayoutStyle;

use Mouf\MVC\BCE\FormRenderers\SubFormFieldWrapperRendererInterface;

use Mouf\MVC\BCE\FormRenderers\SubFormItemWrapperInterface;
use Mouf\MVC\BCE\Classes\ScriptManagers\ScriptManager;
use Mouf\MVC\BCE\FormRenderers\FieldWrapperRendererInterface;
use Mouf\MVC\BCE\BCEFormInstance;
use Mouf\MVC\BCE\BCEForm;

/**
 * This class is the simpliest FieldDescriptor:
 * it handles a field that has no "connections" to other objects (
 * as user name or login for example)
 * @Component
 */
class SubFormFieldDescriptor implements BCEFieldDescriptorInterface {
	
	/**
	 * @var string
	 */
	public $fieldName;
	
	/**
	 * @var string
	 */
	public $fieldLabel;
	
	/**
	 * The description of the field as displayed in the form
	 * @Property
	 * @var string
	 */
	public $description;
	
	/**
	 * @var BCEForm
	 */
	public $form;
	
	/**
	 * @var BCEFormInstance[]
	 */
	private $formInstances = array();
	
	/**
	 * @var BCEFormInstance
	 */
	private $emptyFormInstance = array();

	/**
         * The name of the method of the DAO that returns the list of sub-from beans
         * filtered by the main bean.
         *
         * The method signature must accept a main bean as first and only argument:
         *
         * i.e. method signature is:  function(MyBean $bean)
         *
	 * @var string
	 */
	public $beansGetter;
	
	/**
         * The name of the getter method in the subform bean that returns the main bean.
         *
	 * @var string
	 */
	public $fkGetter;
	
	/**
         * The name of the setter method in the subform bean that sets the main bean.
         *
	 * @var string
	 */
	public $fkSetter;
	
	/**
	 * @var mixed $bean
	 */
	private $beans = array();
	
	/**
	 * The renderer that will display the whole DOM associated to the field (label included)
	 * @Property
	 * @var SubFormFieldWrapperRendererInterface
	 */
	public $fieldWrapperRenderer;
	
	/**
	 * The renderer that will display each item (sub form) individually
	 * @Property
	 * @var SubFormItemWrapperInterface
	 */
	public $itemWrapperRenderer;
	
	/**
	 * The name of the subForm
	 * @see \Mouf\MVC\BCE\Classes\Descriptors\BCEFieldDescriptorInterface::getFieldName()
	 */
	public function getFieldName(){
		return $this->fieldName;
	}
	
	/**
	 * The label of the subform section in the mainform
	 * @see \Mouf\MVC\BCE\Classes\Descriptors\BCEFieldDescriptorInterface::getFieldLabel()
	 */
	public function getFieldLabel(){
		return $this->fieldLabel;
	}
	
	public function load($bean, $id = null, &$form = null){
		//$bean will contain the parent's id go get the child beans
		$this->getBeans($bean, $id, $form);
		$this->form->validationHandler = $form->validationHandler;
		$this->form->scriptManager = $form->scriptManager;
		$this->form->isMain = false;
		$this->form->mode = $form->mode;
		$layout = new LayoutStyle(LayoutStyle::LAYOUT_INLINE);
		$layout->setLayoutRatio(6);
		$this->form->setDefaultLayout($layout);
		
		foreach ($this->beans as $bean){
			$formInstance = new BCEFormInstance();
			$formInstance->form = $this->form;
			$formInstance->baseBean = $bean;
			$formInstance->loadBean();
			$formInstance->beanId = $formInstance->idDescriptorInstance->getFieldValue();
			$formInstance->idDescriptorInstance->setContainerName($this->getFieldName());
			foreach ($formInstance->descriptorInstances as $descInstance){
				/* @var $descInstance FieldDescriptorInstance */
				$descInstance->setContainerName($this->getFieldName());
			}
			$this->formInstances[] = $formInstance;
		}
		
		$this->emptyFormInstance = new BCEFormInstance();
		$this->emptyFormInstance->form = $this->form;
		$this->emptyFormInstance->baseBean = $this->getEmptyBean();
		$this->emptyFormInstance->beanId = 'a';
		$this->emptyFormInstance->loadBean();
		$this->emptyFormInstance->idDescriptorInstance->setContainerName($this->getFieldName());
		foreach ($this->emptyFormInstance->descriptorInstances as $descInstance){
			/* @var $descInstance FieldDescriptorInstance */
			$descInstance->setContainerName($this->getFieldName());
		}
		
		$descriptorInstance = new FieldDescriptorInstance($this, $form, $id);
		$descriptorInstance->value = $this->beans;
		return $descriptorInstance;
	}
	
	private function getBeans($bean, $id = null, $form = null){
		$this->beans = call_user_func(array($this->form->mainDAO, $this->beansGetter), $bean);
	}
	
	private function getEmptyBean(){
		return call_user_func(array($this->form->mainDAO, 'create'));
	}
	
	
	public function addJS(BCEForm & $form, $bean, $id){
		
		$script = "
			var new".$this->fieldName."_index = 1;
			
			function ".$this->getAddItemFonction()."{
				var  fieldId = '".$this->fieldName."';
				var template = jQuery('#' + fieldId + '_template');
				var html = template.text(); 
				while(html.indexOf('\\[a\\]') != -1){
					html = html.replace('\\[a\\]','[__bce__add_' + new".$this->fieldName."_index + ']');
				}
				template.before( html );
				
				jQuery('.sub-form-items.' + fieldId + ' .subform-item').each(function(index){
					jQuery(this).addClass(index % 2 == 0 ? 'odd' : 'even');
				});
				
				new".$this->fieldName."_index ++;
				
				return false;
			}
						
			jQuery(document).on('click', '.subform-item .do-remove', function(){
				jQuery(this).parents('.remove-item').find('input').val(1);
				jQuery(this).parents('.subform-item').find('.undo-remove').css('display', 'block');
				jQuery(this).hide();
		
				jQuery(this).parents('.subform-item').find('button:not(.delete-persist), textarea:not(.delete-persist), input:not(.delete-persist), select:not(.delete-persist)').attr('disabled', true);
			});
		
			jQuery(document).on('click', '.subform-item .undo-remove', function(){
				jQuery(this).parents('.remove-item').find('input').val(0);
				jQuery(this).parents('.subform-item').find('.do-remove').css('display', 'block');
				jQuery(this).hide();
		
				jQuery(this).parents('.subform-item').find('input, select, button, textarea').attr('disabled', false);
			});
		";
		$form->scriptManager->addScript(ScriptManager::SCOPE_WINDOW, $script);
	}
	
	public function getAddItemFonction(){
		return "add_$this->fieldName"."_item()";
	}
	
	public function getRemoveItemFonction(){
		return "remove_$this->fieldName"."_item()";
	}
	
	/**
	 * Returns the Renderer for that bean
	 * return FieldRendererInterface
	 */
	public function toHTML($descriptorInstance, $formMode){
		ob_start();
		$index = "odd";
		echo "<div class='control-group form-group sub-form-items ".$this->getFieldName()."'>";
		echo '<label class="control-label">'.$this->fieldLabel.'</label>';
		echo '<div class="controls">';
		foreach ($this->formInstances as $formInstance){
			$this->itemWrapperRenderer->toHtml($this, $formInstance, $index);
			$index = $index == "odd" ? "even" : "odd";
		}
		echo "<textarea style='display: none' id='" . $this->getFieldName() . "_template'>";
		$this->itemWrapperRenderer->toHtml($this, $this->emptyFormInstance);
		echo "</textarea>";
		echo '<div class="subform-item-add form-horizontal">
					<div class="form-group">
						<div class="col-sm-2">
						</div>
						<div class="col-sm-10">
							<button class="btn" onclick="'.$descriptorInstance->fieldDescriptor->getAddItemFonction().';return false;"><i class="icon icon-plus-sign"></i>&nbsp;Add an Item</button>
						</div>
					</div>
				</div>';
    	echo '</div>';
		echo "</div>";
		$html = ob_get_contents();
		ob_end_clean();
		return $html;
	}
	
	public function preSave($post, BCEForm &$form, $bean, $isNew){
		//TODO if needed
	}
	
	public function postSave($parentBean, $parentBeanId, $postValues = null){
		$data = get($this->getFieldName());
		if (is_array($data)) {
			foreach ($data as $key => $values){
				$isAdd = substr($key, 0, 11) == "__bce__add_";
				if ($values['__bce__delete'] == 0){
					$bean = $isAdd ? $this->form->mainDAO->create() : $this->form->mainDAO->getById($key);
					$this->setParentFK($bean, $parentBeanId); 
					$this->form->save($values, $bean);
				}else if (!$isAdd){
					$bean = $this->form->mainDAO->getById($key);
					$this->form->mainDAO->delete($bean);
				}
			}
		}
	}
	
	private function setParentFK(& $bean, $parentBeanId){
		call_user_func(array($bean, $this->fkSetter), $parentBeanId);
	}
	
	/**
	 * Tells if the field is editable
	 * @return boolean
	 */
	public function canEdit(){
		return true;
	}
	
	/**
	 * Tells if the field's value can be viewed
	 * @return boolean
	 */
	public function canView(){
		return true;
	}
	
	public function getDefaultValue(){
		return null;
	}
	
}