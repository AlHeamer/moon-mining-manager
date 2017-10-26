<!doctype html>

<html lang="{{ app()->getLocale() }}">

    <head>

        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title') &#0183; Moon Mining Manager</title>

        <link rel="stylesheet" href="/css/app.css">

    </head>

    <body>

        <div class="header">
            <h1>Moon Mining Manager</h1>
        </div>

        @include('common.navigation')

        <div class="container">
            @yield('content')
        </div>

        <script src="/js/app.js"></script>

    </body>

</html>