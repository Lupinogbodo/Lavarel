<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Platform API</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .endpoints {
            background: #f7f7f7;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .endpoints h2 {
            color: #667eea;
            font-size: 18px;
            margin-bottom: 15px;
        }
        .endpoint {
            margin-bottom: 10px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        .method {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
            margin-right: 8px;
            font-size: 12px;
        }
        .post { background: #4caf50; color: white; }
        .get { background: #2196f3; color: white; }
        a {
            color: #667eea;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎓 Learning Platform API</h1>
        <p>Welcome to the Learning Platform API. Your API is running successfully!</p>
        
        <div class="endpoints">
            <h2>📍 Available Endpoints</h2>
            
            <div class="endpoint">
                <span class="method post">POST</span>
                <span>/api/v1/register</span> - Register new user
            </div>
            
            <div class="endpoint">
                <span class="method post">POST</span>
                <span>/api/v1/login</span> - User login
            </div>
            
            <div class="endpoint">
                <span class="method get">GET</span>
                <span>/api/v1/search/courses</span> - Search courses
            </div>
            
            <div class="endpoint">
                <span class="method post">POST</span>
                <span>/api/v1/enrollments</span> - Create enrollment 🔒
            </div>
            
            <div class="endpoint">
                <span class="method get">GET</span>
                <span>/api/v1/enrollments</span> - List enrollments 🔒
            </div>
            
            <p style="margin-top: 15px; font-size: 13px;">
                🔒 = Requires authentication
            </p>
        </div>
        
        <p style="margin-top: 20px;">
            <strong>Frontend:</strong> <a href="/index.html">Course Search Interface</a>
        </p>
    </div>
</body>
</html>
