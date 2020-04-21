<?php

namespace Kompo\Komposers;

use Kompo\Core\AuthorizationGuard;
use Kompo\Exceptions\FormMethodNotFoundException;
use Kompo\Exceptions\NotFoundKompoActionException;
use Kompo\Komposers\Query\QueryDisplayer;
use Kompo\Komposers\Form\FormDisplayer;
use Kompo\Komposers\Form\FormManager;
use Kompo\Komposers\Form\FormSubmitter;
use Kompo\Komposers\KomposerManager;
use Kompo\Routing\Dispatcher;
use Kompo\Select;

class KomposerHandler
{
    public static function performAction($komposer)
    {
        switch(request()->header('X-Kompo-Action'))
        {
            case 'eloquent-submit':
                return FormSubmitter::eloquentSave($komposer);

            case 'handle-submit':
                return FormSubmitter::callCustomHandle($komposer);

            case 'post-to-form':
                return FormManager::handlePost($komposer);

            case 'include-komponents':
                return KomposerManager::prepareKomponentsForDisplay($komposer, request()->header('X-Kompo-Method'));

            case 'self-method':
                return null; //TODO

            case 'load-komposer':
                return static::getKomposerFromKomponent($komposer);

            case 'search-options':
                return static::getMatchedSelectOptions($komposer);

            case 'updated-option':
                return static::reloadUpdatedSelectOptions($komposer);

            case 'browse-items':
                return QueryDisplayer::browseCards($komposer);

            case 'order':
                return QueryManager::orderItems($komposer);

            case 'delete-item':
                return static::deleteRecord($komposer);
        }

        throw new NotFoundKompoActionException(get_class($komposer));
    }


    /**
     * Gets the matched select options for Querys or Forms.
     *
     * @param Kompo\Komposers\Komposer $komposer  The parent komposer
     *
     * @throws     FormMethodNotFoundException  (description)
     *
     * @return     <type>                       The matched select options.
     */
    public static function getMatchedSelectOptions($komposer)
    {
        AuthorizationGuard::checkIfAllowedToSeachOptions($komposer);

        if(method_exists($komposer, $method = request('method'))){
            return Select::transformOptions($komposer->{$method}(request('search')));
        }else{
            throw new FormMethodNotFoundException($method);
        }
    }

    /**
     * { function_description }
     *
     * @param Kompo\Komposers\Komposer $komposer  The parent komposer
     *
     * @return     <type>
     */
    public static function reloadUpdatedSelectOptions($komposer)
    {
        foreach (KomposerManager::collectFields($komposer) as $field) {

            if($field->name == request()->header('X-Komponent')){

                return $field->options;

            }
        }
    }

    /**
     * Gets the form or query class from komponent and returns it booted.
     *
     * @param Kompo\Komposers\Komposer $komposer  The parent komposer
     *
     * @return Kompo\Komposers\Komposer
     */
    public static function getKomposerFromKomponent($komposer)
    {
        return with(new Dispatcher(request()->header('X-Kompo-Class')))->bootFromRoute();
    }

    /**
     * Deletes a database record
     * 
     * @param  string|integer $id [Object's key]
     * @return \Illuminate\Http\Response     [redirects back to current page]
     */
    public static function deleteRecord($komposer)
    {
        $deleteKey = request('store.deleteKey');

        $record = $komposer->model->newInstance()->findOrFail($deleteKey);

        if( 
            (method_exists($record, 'deletable') && $record->deletable()) 
            || 
            (defined(get_class($record).'::DELETABLE_BY') && $record::DELETABLE_BY &&
                optional(auth()->user())->hasRole($record::DELETABLE_BY))
            
            /* Controversial...
            || optional(auth()->user())->hasRole('super-admin')*/
        ){
            $record->delete();
            return 'deleted!';
        }

        return abort(403, __('Sorry, you are not authorized to delete this item.'));
    }

}