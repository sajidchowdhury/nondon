@props(['active' => false])

<a
    {{ $attributes->merge(['class' => 'inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium ' . ($active ? 'border-indigo-500 text-gray-900 dark:text-white' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200')]) }}
>
    {{ $slot }}
</a>
