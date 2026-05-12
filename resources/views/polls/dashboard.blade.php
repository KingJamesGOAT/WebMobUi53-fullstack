<x-vue-app-layout>
    <x-slot:scripts>
        @vite(['resources/css/app.css', 'resources/js/poll-dashboard.js'])
    </x-slot>

    <div
        id="app"
        data-props='@json([
            "polls" => $polls,
            "loginUrl" => route("login"),
            "username" => Auth::user()->username ?? "Utilisateur"
        ])'
    ></div>
</x-vue-app-layout>
