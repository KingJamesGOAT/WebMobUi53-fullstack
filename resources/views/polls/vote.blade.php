<x-vue-app-layout>
    <x-slot:scripts>
        @vite(['resources/css/app.css', 'resources/js/poll-vote.js'])
    </x-slot>

    <div id="app-vote" data-token="{{ request()->route('token') }}"></div>
</x-vue-app-layout>
