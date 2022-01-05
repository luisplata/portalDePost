<!DOCTYPE HTML>
<!--
 Editorial by HTML5 UP
 html5up.net | @ajlkn
 Free for personal and commercial use under the CCA 3.0 license (html5up.net/license)
-->
<html>

<head>
    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ env('CLAVE_DE_GOOGLE_ANALYTICS') }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());

        gtag('config', '{{ env('CLAVE_DE_GOOGLE_ANALYTICS') }}');

    </script>
    <title>{{ env('APP_NAME') }}</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
    <link rel="stylesheet" href="{{ url('') }}/assets/css/main.css?v{{ date('Ymdhs') }}" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css?v<?= date('Ymdhs') ?>" rel="stylesheet"
        integrity="sha384-giJF6kkoqNQ00vy+HMDP7azOuL0xtbfIcaT9wjKHr8RbDVddVHyTfAAsrekwKmP1" crossorigin="anonymous">
</head>

<body class="is-preload">

    <!-- Wrapper -->
    <div id="wrapper">

        <!-- Main -->
        <div id="main">
            <div class="inner">
    <!-- Header -->
                <header id="header">
                    <h1><a href="{{ env('HOME') }}PPV" class="logo"><strong>{{ env('APP_ALTER_NAME') }}</strong></a></h1>
                    <ul class="icons">
                        <li><a href="{{ env('HOME') }}" target="" class="logo"><span
                                    class="label">{{ env('APP_NAME') }}</span></a></li>
                        <li><a href="{{ env('TELEGRAM', '#') }}" target="_blank" class="icon brands fa-telegram"><span
                                    class="label">Telegram</span></a></li>
                        <li><a href="{{ env('DISCORD', '#') }}" target="_blank" class="icon brands fa-discord"><span
                                    class="label">Discord</span></a></li>
                        <li><a href="{{ env('REDDIT', '#') }}" target="_blank" class="icon brands fa-reddit"><span
                                    class="label">Reddit</span></a>
                        </li>

                        <li><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="darkMode"><label class="custom-control-label" for="darkMode">Dark Mode</label></div></li>
                        
                    </ul>
                </header>

                <!-- Content -->
                <section>
                    <header class="main">
                        <h1>{{ $stream->nombre }}</h1>
                    </header>

                    <span class="image main">
                        <iframe src="{{$stream->url}}" frameborder="0" style='width:100%;height: 575px;'></iframe>
                    </span>
                </section>
            </div>
            
                 <!-- Banner -->
                 <section id="banner">
                    <div class="row row-cols-2 row-cols-sm-3">
                        @foreach ($streams as $pack)
                            <div class="col-3">
                                <a href="{{ env('HOME') }}PPV/{{ $pack->ConvertNameNormalToUrl() }}">
                                    <div class="card bg-dark center">
                                        <img src="{{ $pack->imagen }}" class="card-img">
                                        <div class="card-img-overlay text-center">
                                            <h2 class="card-title">{{ $pack->nombre }}</h2>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </section>
        </div>
        
<!-- Sidebar -->
<div id="sidebar">
            <div class="inner">
                <!-- Section -->


                <!-- Section -->
                <section>
                    <header class="major">
                        <h2>Chanels</h2>
                    </header>
                    <ul class="contact">
                        <li class="icon brands fa-telegram"><a
                                href="{{ env('TELEGRAM', '#') }}">{{ env('TELEGRAM', 'TELEGRAM') }}</a></li>
                        <li class="icon brands fa-discord"><a
                                href="{{ env('DISCORD', '#') }}">{{ env('DISCORD', 'DISCORD') }}</a></li>
                        <li class="icon brands fa-reddit"><a
                                href="{{ env('REDDIT', '#') }}">{{ env('REDDIT', 'REDDIT') }}</a></li>
                    </ul>
                </section>
                
                <section>
                    <header class="major">
                        <h2>Tutorial</h2>
                    </header>
                    <ul class="contact">
                        <li class="icon solid fa-mobile-alt"><a
                                href="{{ env('TUTORIAL_MOVIL', '#') }}">FOR MOBILE</a></li>
                        <li class="icon solid fa-desktop"><a
                                href="{{ env('TUTORIAL_PC', '#') }}">FOR DESKTOP</a></li>
                    </ul>
                </section>

                <!-- Footer -->
                <footer id="footer">
                    <p class="copyright">&copy; {{ env('APP_NAME') }}</p>
                </footer>

            </div>
        </div>

    </div>

    <!-- Scripts -->
    <script src="{{ url('') }}/assets/js/jquery.min.js"></script>
    <script src="{{ url('') }}/assets/js/browser.min.js"></script>
    <script src="{{ url('') }}/assets/js/breakpoints.min.js"></script>
    <script src="{{ url('') }}/assets/js/util.js"></script>
    <script src="{{ url('') }}/assets/js/main.js"></script>
    <script src="{{ url('') }}/assets/js/DarkMode.js"></script>

    <script>
        function RegistrarVisita() {
        
            fetch('{{url('api/registrarVisita')}}/'+{{ $stream->id }},{
                    method:'get'
                })
                .then(response => {})
                .then(html => {})
                .catch(error => console.log(error))
        
        }
                                    //Min       *seg     *milisecons
        setTimeout(RegistrarVisita, 1           *60      *1000);
    </script>
</body>

</html>
