<!DOCTYPE html>
<html lang="es">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

        <!-- Bootstrap CSS -->
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta/css/bootstrap.min.css" integrity="sha384-/Y6pD6FV/Vv2HJnA6t+vslU6fwYXjCFtcEpHbNJ0lyAFsXTsjBbfaDjzALeQsN6M" crossorigin="anonymous">
        <style>
            .contenido-medio{
                margin-bottom: 50px;
                margin-top: 50px;
            }
            .imagenes-portada img{
                width: 100%;
            }
            .articulo-principal-contenido{
                margin-bottom: 30px;
            }
        </style>
    </head>
    <body style="background-image: url('{{asset('images/fondo-1.jpg')}}');">
        <!-- Navbar parte superior de la pagina -->
        <nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background-color: #c90000">
            <div class="container">
                <a class="navbar-brand" href="{{url("/")}}">
                    <img src="http://www.rafastienda.com/web/wp-content/uploads/2017/05/rafas-tienda-web-logo.jpg" height="70" alt=""> 
                </a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse h5" id="navbarSupportedContent">
                    <ul class=" mr-auto"></ul>
                    <ul class="navbar-nav">
                        <li class="nav-item active">
                            <a class="nav-link" href="{{url('/')}}">Inicio</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{url('/quienes-somos')}}">Quienes Somos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{url('/portafolio')}}">Portafolio</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{url('/faqs')}}">Preguntas Frecuentes</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        <!-- / parte superior de la pagina -->


        <!-- Carrusel -->
        <div id="carouselExampleIndicators" class="carousel slide" data-ride="carousel">
            <ol class="carousel-indicators">
                <li data-target="#carouselExampleIndicators" data-slide-to="0" class="active"></li>
                <li data-target="#carouselExampleIndicators" data-slide-to="1"></li>
                <li data-target="#carouselExampleIndicators" data-slide-to="2"></li>
            </ol>
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <img class="d-block w-100" src="{{asset('images/banner/artesanias-mochilas-tejido.jpg')}}" alt="First slide">
                </div>
                <div class="carousel-item">
                    <img class="d-block w-100" src="{{asset('images/banner/mochilas-wayuu-pintadas.jpg')}}" alt="First slide">
                </div>
                <div class="carousel-item">
                    <img class="d-block w-100" src="{{asset('images/banner/mochilas-wayuu.jpg')}}" alt="First slide">
                </div>
            </div>
            <a class="carousel-control-prev" href="#carouselExampleIndicators" role="button" data-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="sr-only">Previous</span>
            </a>
            <a class="carousel-control-next" href="#carouselExampleIndicators" role="button" data-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="sr-only">Next</span>
            </a>
        </div>
        <!-- / Carrusel -->
        <div class="container">
            <div class="row contenido-medio">
                <div class="col-md-6 text-center imagenes-portada">

                    <a href="{{ url('portafolio') }}">
                        <img src="{{asset('images/portafolio-productos-wayuu.png')}}">
                    </a>
                </div>
                <div class="col-md-6 text-center imagenes-portada">
                    <a target="_blank" href="http://www.rafastienda.com/portafolio/rafastienda.html">
                        <img src="{{asset('images/catalogo-productos.png')}}">
                    </a>
                </div>
            </div>
            <hr/>
            <div class="row text-center">
                <div class="col-md-6 text-left">
                    <div>
                        <span class="h3">
                            Mochilas Wayu
                        </span>
                    </div>
                    <a href="{{url('/portafolio')}}">
                        <div class="articulo-principal-contenido">
                            <p>

                            </p>
                            <img class="d-block w-100" src="{{asset('images/carteras-mochilas-wayuu-arhucos.jpg')}}">
                        </div>
                    </a>
                </div>
                <div class="col-md-6 text-left">

                    <div>
                        <span class="h3">
                            Cultura Wayu
                        </span>
                    </div>
                    <a href="{{url('/quienes-somos')}}">
                        <div class="articulo-principal-contenido">
                            <p>
                            </p>
                            <img class="d-block w-100" src="{{asset('images/mantas-guajiras.jpg')}}">
                        </div>
                    </a>
                </div>
            </div>
            <div class="row">
                <div class="col text-center" style="background-image: url('{{asset('images/rafastore-cartagena-atersanias.jpg')}}');background-repeat: no-repeat">

                    <br/>
                    <span class="h3">
                        Los mejores productos Wayuú
                    </span>
                    <p>
                        En un solo lugar de Colombia
                    </p>

                    <br/>
                    <br/>

                </div>
            </div>
        </div>
        <!-- Liks para el portafolio y la revista-->
		@include("plantilla.app")



        <!-- Liks para el portafolio y la revista-->
        <!-- Optional JavaScript -->
        <!-- jQuery first, then Popper.js, then Bootstrap JS -->
        <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.11.0/umd/popper.min.js" integrity="sha384-b/U6ypiBEHpOf/4+1nzFpr53nxSS+GLCkfwBdFNTxtclqqenISfwAzpKaMNFNmj4" crossorigin="anonymous"></script>
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta/js/bootstrap.min.js" integrity="sha384-h0AbiXch4ZDo7tp9hKZ4TsHbi047NrKGLO3SEJAg45jXxnGIfYzk4Si90RDIqNm1" crossorigin="anonymous"></script>
        <script src="https://unpkg.com/i18next@9.0.0/i18next.min.js"></script>
    </body>
</html>