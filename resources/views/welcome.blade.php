<!DOCTYPE HTML>
<!--
 Editorial by HTML5 UP
 html5up.net | @ajlkn
 Free for personal and commercial use under the CCA 3.0 license (html5up.net/license)
-->
<html>

<head>
    <title>{{ env('APP_NAME') }}</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
    <link rel="stylesheet" href="{{url('')}}/assets/css/main.css?v<?=date('Ymdhs')?>" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet"
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
                    <h1><a href="{{ env('HOME') }}" class="logo"><strong>{{ env('APP_NAME') }}</strong></a></h1>
                    <ul class="icons">
                        <li><a href="{{ env('TELEGRAM', '#') }}" target="_blank" class="icon brands fa-telegram"><span
                                    class="label">Telegram</span></a></li>
                        <li><a href="{{ env('DISCORD', '#') }}" target="_blank" class="icon brands fa-discord"><span
                                    class="label">Discord</span></a></li>
                        <li><a href="{{ env('REDDIT', '#') }}" target="_blank" class="icon brands fa-reddit"><span
                                    class="label">Reddit</span></a>
                        </li>
                    </ul>
                </header>

                <!-- Banner -->
                <section id="banner container">
                    <div class="row row-cols-2 row-cols-sm-3">
                        @foreach ($banners as $pack)
                            <div class="col">
                                <a href="{{ env('HOME') }}content/{{ $pack->ConvertNameNormalToUrl() }}">
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

                <!-- Section -->
                <section class="row">
                    <div class="row row-cols-1 row-cols-sm-2" id="contentPacks">
                        @include('ScrollInfinite')
                        <div class="text-center col-12" id="cargando" ><span class="h1">CARGANDO...</span></div>
                    </div>
                </section>

            </div>
        </div>

        <!-- Sidebar -->
        <div id="sidebar">
            <div class="inner">
                <!-- Section -->
                <section>
                    <header class="major">
                        <h2>Hot Post</h2>
                    </header>
                    <div class="mini-posts">
                        @foreach ($hot as $pack)
                            <article>
                                <a href="{{ env('HOME') }}content/{{ $pack->ConvertNameNormalToUrl() }}">
                                    <div class="card bg-dark text-white center">
                                        <img src="{{ $pack->imagen }}" class="card-img">
                                        <div class="card-img-overlay text-center">
                                            <h5 class="card-title">{{ $pack->nombre }}</h5>
                                        </div>
                                    </div>
                                </a>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section>
                    <header class="major">
                        <h2>Popular Post</h2>
                    </header>
                    <div class="mini-posts">
                        @foreach ($popular as $pack)
                            <article>
                                <a href="{{ env('HOME') }}content/{{ $pack->ConvertNameNormalToUrl() }}">
                                    <div class="card bg-dark text-white center">
                                        <img src="{{ $pack->imagen }}" class="card-img">
                                        <div class="card-img-overlay text-center">
                                            <h5 class="card-title">{{ $pack->nombre }}</h5>
                                        </div>
                                    </div>
                                </a>
                            </article>
                        @endforeach
                    </div>
                </section>

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
    <script src="{{url('')}}/assets/js/jquery-3.5.1.min.js"></script>
    <script src="{{url('')}}/assets/js/browser.min.js"></script>
    <script src="{{url('')}}/assets/js/breakpoints.min.js"></script>
    <script src="{{url('')}}/assets/js/util.js"></script>
    <script src="{{url('')}}/assets/js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ygbV9kiqUc6oa4msXn9868pTtWMgiQaeYH7/t7LECLbyPA2x65Kgf80OJFdroafW" crossorigin="anonymous">
    </script>
    <script>
        let pagina        =     2
        const cargando    =     document.getElementById("cargando")
        let isLoad = false
        window.onscroll   =     () =>{
            cargando.removeAttribute('hidden')
            //console.log(window.innerHeight + window.pageYOffset + 20 , document.body.offsetHeight)
            if((window.innerHeight + window.pageYOffset + 20) >= document.body.offsetHeight && !isLoad){
                isLoad = true
                fetch('{{url('')}}/api/infiniteScroll?page='+pagina,{
                    method:'get'
                })
                .then(response => response.text() )
                .then(html => {
                    cargando.setAttribute('hidden','hidden')
                    document.getElementById("contentPacks").innerHTML += html
                    pagina++;
                    isLoad = false                
                })
                .catch(error => console.log(error))
            }
        }
    </script>

</body>

</html>
