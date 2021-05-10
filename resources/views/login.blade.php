<!doctype html>

<html lang="{{ app()->getLocale() }}">

    <head>

        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Brave Newbies Inc. - Login with EVE SSO</title>

        <link rel="stylesheet" href="/assets/bravecollective/web-ui/css/brave.css">

    </head>

    <body>

        <div class="container">
            <div class="jumbotron mt-5 text-light bg-dark-4">
                <div class="row justify-content-center">
                    <div class="col col-lg-5">
                        <h1 class="display-4">Moon Mining Manager</h1>
                        <hr class="my-4">
                        <p>Login with your EVE Online Account to gain access.</p>
                        <a href="/sso" role="button">
                            <img src="/assets/bravecollective/web-ui/images/EVE_SSO_Login_Buttons_Large_Black.png"
                                 alt="LOG IN with EVE Online" />
                        </a>
                    </div>
                    <div class="col col-lg-4">
                        <img src="/assets/bravecollective/web-ui/images/logo_vector.svg"
                             alt="Brave Collective Logo" class="img-fluid" />
                    </div>
                </div>
            </div>
            <footer class="navbar navbar-dark bg-brave shadow-1">
                <div class="align-self-center">
                    Brave Collective Services. For support write to support@bravecollective.freshdesk.com or
                    ask in the ingame channel "Brave IT Team".
                </div>
            </footer>
        </div>

    </body>

</html>
