<?php

namespace Kompo\Tests\Unit\Interaction;

use Illuminate\Foundation\Http\FormRequest;

class _InteractionPostRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            //
        ];
    }
}
