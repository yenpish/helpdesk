<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - Helpdesk</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f5f7;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #222;
        }

        .login-box {
            width: 100%;
            max-width: 400px;
            background: white;
            border: 1px solid #ddd;
            padding: 32px;
            box-sizing: border-box;
        }

        h1 {
            margin: 0 0 6px;
            font-size: 28px;
        }

        .subtitle {
            margin: 0 0 28px;
            color: #666;
            font-size: 14px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: bold;
        }

        .field {
            margin-bottom: 18px;
        }

        input {
            width: 100%;
            box-sizing: border-box;
            padding: 11px 12px;
            border: 1px solid #bbb;
            font-size: 15px;
            border-radius: 3px;
        }

        input:focus {
            outline: none;
            border-color: #555;
        }

        button {
            width: 100%;
            padding: 11px;
            border: none;
            border-radius: 3px;
            background: #222;
            color: white;
            font-size: 15px;
            cursor: pointer;
        }

        button:hover {
            background: #333;
        }
    </style>
</head>

<body>

<div class="login-box">

    <h1>Helpdesk</h1>

    <p class="subtitle">
        Sign in to access your account
    </p>

    <form method="POST" action="{{ route('login') }}">

        @csrf

        <div class="field">
            <label for="email">Email</label>

            <input
                id="email"
                type="email"
                name="email"
                placeholder="Enter your email"
                required
            >
        </div>

        <div class="field">
            <label for="password">Password</label>

            <input
                id="password"
                type="password"
                name="password"
                placeholder="Enter your password"
                required
            >
        </div>

        <button type="submit">
            Sign In
        </button>

    </form>

</div>

</body>
</html>
