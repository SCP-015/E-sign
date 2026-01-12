<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Sign API Documentation</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 20px;
            margin: -20px -20px 40px -20px;
            border-radius: 0 0 10px 10px;
        }

        header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        header p {
            font-size: 1.1em;
            opacity: 0.9;
        }

        .toc {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .toc h2 {
            font-size: 1.3em;
            margin-bottom: 15px;
            color: #667eea;
        }

        .toc ul {
            list-style: none;
        }

        .toc li {
            margin: 8px 0;
        }

        .toc a {
            color: #667eea;
            text-decoration: none;
            transition: color 0.3s;
        }

        .toc a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .content {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        h2 {
            color: #667eea;
            margin-top: 40px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        h3 {
            color: #764ba2;
            margin-top: 25px;
            margin-bottom: 15px;
        }

        h4 {
            color: #555;
            margin-top: 15px;
            margin-bottom: 10px;
        }

        p {
            margin-bottom: 15px;
            line-height: 1.8;
        }

        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #d63384;
        }

        pre {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            line-height: 1.5;
        }

        pre code {
            background: none;
            color: inherit;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        table th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
        }

        table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }

        table tr:hover {
            background: #f9f9f9;
        }

        .endpoint {
            background: #f9f9f9;
            padding: 15px;
            border-left: 4px solid #667eea;
            margin: 15px 0;
            border-radius: 4px;
        }

        .method {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: bold;
            margin-right: 10px;
            font-size: 0.9em;
        }

        .method.get {
            background: #61affe;
            color: white;
        }

        .method.post {
            background: #49cc90;
            color: white;
        }

        .method.put {
            background: #fca130;
            color: white;
        }

        .method.delete {
            background: #f93e3e;
            color: white;
        }

        .path {
            font-family: 'Courier New', monospace;
            background: #f4f4f4;
            padding: 4px 8px;
            border-radius: 3px;
            font-weight: bold;
        }

        .response-code {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-weight: bold;
            margin-right: 10px;
        }

        .response-code.success {
            background: #d4edda;
            color: #155724;
        }

        .response-code.error {
            background: #f8d7da;
            color: #721c24;
        }

        .note {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .note strong {
            color: #1976D2;
        }

        ul, ol {
            margin-left: 20px;
            margin-bottom: 15px;
        }

        li {
            margin-bottom: 8px;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 0.9em;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            header {
                margin: -10px -10px 30px -10px;
                padding: 20px 10px;
            }

            header h1 {
                font-size: 1.8em;
            }

            .content {
                padding: 20px;
            }

            pre {
                font-size: 0.8em;
            }

            table {
                display: block;
                width: 100%;
                overflow-x: auto;
            }

            table th,
            table td {
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>📋 E-Sign API Documentation</h1>
            <p>Complete API Reference for Mobile & Web Developers</p>
        </div>
    </header>

    <div class="container">
        <div class="toc">
            <h2>📑 Quick Navigation</h2>
            <ul>
                <li><a href="#authentication">🔐 Authentication</a></li>
                <li><a href="#auth-endpoints">🔑 Auth Endpoints</a></li>
                <li><a href="#user-endpoints">👤 User Endpoints</a></li>
                <li><a href="#kyc-endpoints">📝 KYC Endpoints</a></li>
                <li><a href="#documents-endpoints">📄 Documents Endpoints</a></li>
                <li><a href="#certificate-endpoints">🎖️ Certificate Endpoints</a></li>
                <li><a href="#error-codes">⚠️ Error Codes</a></li>
                <li><a href="#testing">🧪 Testing Guide</a></li>
            </ul>
        </div>

        <div class="content">
            {!! \Parsedown::instance()->text($markdown) !!}
            
            <div class="footer">
                <p>E-Sign API Documentation | Last Updated: January 8, 2026</p>
                <p>For questions or issues, contact the development team.</p>
            </div>
        </div>
    </div>

    <script>
        // Add smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        // Add copy to clipboard for code blocks
        document.querySelectorAll('pre').forEach(block => {
            const button = document.createElement('button');
            button.textContent = 'Copy';
            button.style.cssText = 'position: absolute; top: 5px; right: 5px; padding: 5px 10px; background: #667eea; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 0.8em;';
            
            block.style.position = 'relative';
            block.appendChild(button);
            
            button.addEventListener('click', () => {
                const code = block.querySelector('code').textContent;
                navigator.clipboard.writeText(code).then(() => {
                    button.textContent = 'Copied!';
                    setTimeout(() => button.textContent = 'Copy', 2000);
                });
            });
        });
    </script>
</body>
</html>
