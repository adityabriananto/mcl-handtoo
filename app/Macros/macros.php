<?php

use Spatie\Html\Html;


Html::macro('tailwindInputText', function($name, $label) {
    return '
        <div class="w-full">
            <label for="' . $name . '">' . $label . '</label>
            <input type="text" class="
                form-control
                block
                w-full
                px-4
                py-2
                text-base
                font-normal
                text-gray-700
                bg-white bg-clip-padding
                border border-solid border-gray-300
                rounded-full
                transition
                ease-in-out
                m-0
                focus:text-gray-700 focus:bg-white focus:border-blue-600 focus:outline-none
              " name="' . $name . '">
        </div>'
    ;
});

Html::macro('tailwindInputTextRequired', function($name, $label) {
    return '
        <div class="w-full">
            <label for="' . $name . '">' . $label . '</label>
            <input type="text" class="
                form-control
                block
                w-full
                px-4
                py-2
                text-base
                font-normal
                text-gray-700
                bg-white bg-clip-padding
                border border-solid border-gray-300
                rounded-full
                transition
                ease-in-out
                m-0
                focus:text-gray-700 focus:bg-white focus:border-blue-600 focus:outline-none
              " name="' . $name . '" required>
        </div>'
    ;
});

Html::macro('tailwindInputTextarea', function($name, $label) {
    return '
        <div class="w-full">
            <label for="' . $name . '">' . $label . '</label>
            <textarea type="text" class="
                form-control
                block
                w-full
                px-4
                py-2
                text-base
                font-normal
                text-gray-700
                bg-white bg-clip-padding
                border border-solid border-gray-300
                rounded-full
                transition
                ease-in-out
                m-0
                focus:text-gray-700 focus:bg-white focus:border-blue-600 focus:outline-none
              " name="' . $name . '">
              </textarea>
        </div>'
    ;
});

Html::macro('tailwindInputSelect', function($name, $label, $options) {
    $html = '
        <div class="w-full">
            <label for="' . $name . '">' . $label . '</label>
            <select type="text" class="
                form-control
                block
                w-full
                px-4
                py-2
                text-base
                font-normal
                text-gray-700
                bg-white bg-clip-padding
                border border-solid border-gray-300
                rounded-full
                transition
                ease-in-out
                m-0
                focus:text-gray-700 focus:bg-white focus:border-blue-600 focus:outline-none
              " name="' . $name . '">';

        foreach ($options as $value => $text) {
            $html .= '<option value="' . $value . '">' . $text . '</option>';
        }

        $html .=
              '</select>
        </div>';

        return $html;
    ;
});

Html::macro('tailwindInputSelectRequired', function($name, $label, $options, $value = null) {
    // Gunakan fungsi old() untuk mendapatkan nilai input sebelumnya
    // Jika tidak ada, gunakan nilai $value yang diberikan saat pemanggilan makro
    $oldValue = old($name, $value);

    $html = '
        <div class="w-full">
            <label for="' . $name . '">' . $label . '</label>
            <select
                type="text"
                class="
                    form-control
                    block
                    w-full
                    px-4
                    py-2
                    text-base
                    font-normal
                    text-gray-700
                    bg-white bg-clip-padding
                    border border-solid border-gray-300
                    rounded-full
                    transition
                    ease-in-out
                    m-0
                    focus:text-gray-700 focus:bg-white focus:border-blue-600 focus:outline-none
                "
                name="' . $name . '"
                id="' . $name . '"
                required
            >';

        foreach ($options as $key => $text) {
            // Tambahkan kondisi untuk menandai opsi yang harus dipilih (selected)
            $selected = ($key == $oldValue) ? 'selected' : '';
            $html .= '<option value="' . $key . '" ' . $selected . '>' . $text . '</option>';
        }

        $html .=
              '</select>
        </div>';

        return $html;
    ;
});

Html::macro('tailwindButtonSubmit', function($buttonName) {
    return '
        <button type="submit"
                class="inline-block px-6 py-2.5 bg-blue-600 text-white font-medium text-xs leading-tight uppercase rounded-full shadow-md hover:bg-blue-700 hover:shadow-lg focus:bg-blue-700 focus:shadow-lg focus:outline-none focus:ring-0 active:bg-blue-800 active:shadow-lg transition duration-150 ease-in-out cursor-pointer">
            ' . $buttonName . '
        </button>'
    ;
});

Html::macro('tailwindGenericButton', function($buttonName, $id = '', $color = 'blue') {
    return '
        <button type="button" id="' . $id .'"
                class="inline-block px-6 py-2.5 bg-'.$color.'-600 text-white font-medium text-xs leading-tight uppercase rounded-full shadow-md hover:bg-'.$color.'-700 hover:shadow-lg focus:bg-'.$color.'-700 focus:shadow-lg focus:outline-none focus:ring-0 active:bg-'.$color.'-800 active:shadow-lg transition duration-150 ease-in-out cursor-pointer">
            ' . $buttonName . '
        </button>'
    ;
});
