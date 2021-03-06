<?php

namespace Kompo;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Support\Str;
use Kompo\Core\KompoTarget;
use Kompo\Core\RequestData;
use Kompo\Core\ValidationManager;
use Kompo\Database\Lineage;
use Kompo\Exceptions\NoMultiFormClassException;
use Kompo\Komponents\Field;
use Kompo\Komponents\Traits\HasAddLabel;
use Kompo\Komposers\Form\FormBooter;
use Kompo\Komposers\Form\FormSubmitter;
use Kompo\Routing\RouteFinder;

class MultiForm extends Field
{
    use HasAddLabel;

    public $vueComponent = 'MultiForm';

    public $komponents;

    public $multiple = true;

    protected $formClass;

    protected $childStore;

    protected static $multiFormKey = 'multiFormKey';

    protected function vlInitialize($name)
    {
        parent::vlInitialize($name);
        $this->name = lcfirst(Str::camel($name));

        $this->addLabel('Add a new item');
    }

    protected function prepareChildForm($parentForm, $model = null)
    {
        if(!($formClass  = $this->formClass))
            throw new NoMultiFormClassException($this->name);

        $childForm = new $formClass($model, $this->childStore);
        $childForm->{static::$multiFormKey} = $model ? $model->getKey() : null;

        //Pass rules upstream
        ValidationManager::addRulesToKomposer(
            
            collect(ValidationManager::getRules($childForm))->flatMap(function($v, $k){

                return [($this->name.'.*.'.$k) => $v];

            })->all(), 

            $parentForm
        );

        return $childForm;
    }

    public function mounted($parentForm)
    {
        $this->komponents = !$this->value ? [ $this->prepareChildForm($parentForm) ] : 

            $this->value->map(function($item) use($parentForm) {

                return $this->prepareChildForm($parentForm, $item);

            })->all();      
    }


    public function setRelationFromRequest($requestName, $name, $model, $key = null)
    {
        collect(RequestData::get($requestName))->each(function($subrequest, $subKey) use($model, $requestName) {

            $form = FormBooter::bootForAction([
                'kompoClass' => $this->formClass,
                'store' => $this->childStore,
                'parameters' => [], // is this feature needed?
                'modelKey' => $subrequest[static::$multiFormKey] ?? ($this->value[$subKey] ?? null)
            ]);

            //No Validation or Authorization step - it has already been done on the parent Form
            if(Lineage::isOneToMany($model, $requestName)){
                $relation = Lineage::findRelation($model, $requestName);
                $form->model->{$relation->getForeignKeyName()} = $model->id;
            }

            //If all fields are null, don't create a relation for nothing, unless user configured it to do so
            if(collect($subrequest)->filter()->count() == 0 && !$this->acceptsNullRelations())
                return;

            //Then we swap the requests for save
            $mainRequest = request();            
            $subrequest = new Request($subrequest);

            RequestFacade::swap($subrequest);

            FormSubmitter::saveModel($form);

            RequestFacade::swap($mainRequest); //then swap back the original

        });
    }

    /**
     * Sets the fully qualified class name of the form that will be loaded from the Back-end or displayed multiple times when displaying relationships.
     *
     * @param string  $formClass  The fully qualified form class. Ex: App\Http\Komposers\MyForm::class
     * @param array|null  $ajaxPayload  Associative array of custom data to include in the form's store (optional).
     */
    public function formClass($formClass, $ajaxPayload = null)
    {
        $this->formClass = $formClass;
        $this->childStore = $ajaxPayload ?: [];

        return $this->config(array_merge([
            'route' => RouteFinder::getKompoRoute(),
            'routeMethod' => 'POST', //had to be POST to send ajaxPayload
            'ajaxPayload' => $ajaxPayload,
            'sessionTimeoutMessage' => __('sessionTimeoutMessage')
        ],
            KompoTarget::getEncryptedArray($formClass)
        ));
    }


    public function asTable($headers = [])
    {
        $this->headers = $headers;
        return $this;
    }

    public function noAdding()
    {
        return $this->config([
            'noAdding' => true
        ]);
    }

    public function acceptNullRelations()
    {
        return $this->config([
            'acceptNullRelations' => true
        ]);
    }

    protected function isNotAdding()
    {
        return $this->config('noAdding');
    }

    protected function acceptsNullRelations()
    {
        return $this->config('acceptNullRelations');
    }

}
