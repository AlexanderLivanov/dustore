<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Mini Browser</title>
</head>

<body>

    <input
        id="url"
        placeholder="https://example.com"
        style="width:500px">

    <button id="go">Открыть</button>

    <hr>

    <iframe
        id="frame"
        style="width:100%;height:800px;border:1px solid #aaa;"></iframe>

    <script>
        document.getElementById("go").onclick = async () => {

            const url = document.getElementById("url").value;

            const form = new FormData();
            form.append("url", url);

            const html = await fetch("proxy.php", {
                method: "POST",
                body: form
            }).then(r => r.text());

            document.getElementById("frame").srcdoc = html;

        };
    </script>

</body>

</html>