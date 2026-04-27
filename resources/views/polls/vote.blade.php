<x-vue-app-layout>
    <x-slot:scripts>
        @vite(['resources/js/poll-vote.js'])
    </x-slot>

    <x-slot:title>
        Voter
    </x-slot>

    <div id="app-vote" data-token="{{ $token }}"></div>
</x-vue-app-layout>
