<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout & Order GraphQL Playground</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/graphql-playground-react/build/static/css/index.css">
    <style>
        body { margin: 0; overflow: hidden; }
        #root { height: 100vh; }
    </style>
</head>
<body>
    <div id="root"></div>
    <script src="https://cdn.jsdelivr.net/npm/graphql-playground-react/build/static/js/middleware.js"></script>
    <script>
        window.addEventListener('load', function () {
            GraphQLPlayground.init(document.getElementById('root'), {
                endpoint: '/api/graphql',
                headers: {
                    'X-IAE-KEY': '{{ config('services.iae.api_key', '102022400268') }}'
                },
                tabs: [{
                    endpoint: '/api/graphql',
                    query: 'query Order($id: ID!) { order(id: $id) { id invoice_number status total_amount items { product_id quantity price subtotal } } }',
                    variables: JSON.stringify({ id: 1 }, null, 2)
                }]
            });
        });
    </script>
</body>
</html>
