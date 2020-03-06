<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>See Tweets!</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="main.css" rel="stylesheet"/>
    <script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
</head>
<body>
<div>
    <div id="inputWrap">
        <input id="input" placeholder="enter twitter handle" />
        <button id="button" onclick="triggerUpdate()">GO</button>
    </div>
    <div id="outputWrap" style="display:none">
        <div><span>Latest Tweets from </span><span id="handle"></span></div>
        <div><button onclick="startAgain()">search another</button></div>
        <div id="output"></div>
    </div>
</div>
<script>
    const $button = document.querySelector("#button");
    function startAgain(){
        window.location.reload();
    }
    function triggerUpdate(){
        const input = $("#input").val();
        if(!input){
            alert('please enter a handle');
            return false;
        }

        getData('//redjapan.hk/timeline_webapp/api/index.php?screen_name='+input)
            .then((data) => {
                if(data.length){
                    $("#inputWrap").hide();
                    $("#handle").text(input);
                    $("#outputWrap").show();
                    $("#output").html(data);
                } else {
                    alert('an error occurred');
                }
            });
    }
    async function getData(url) {
        const response = await fetch(url, {
            method: 'GET',
            mode: 'cors',
            cache: 'no-cache',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            redirect: 'follow',
            referrerPolicy: 'no-referrer',
        });
        return await response.json();
    }

</script>
</body>
</html>
