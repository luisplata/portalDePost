window.onload = function() {
    InitSearch()
}

//Search component
function InitSearch(){
    document.querySelector("#search_button").onclick = function(){
        console.log(pagePrincipal)
        var search = document.querySelector("#search")
        window.location.href = pagePrincipal+"/search/"+search.value;
    }
}