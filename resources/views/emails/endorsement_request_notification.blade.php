<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Endorsement Request Notification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #fff;
            box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1);
        }

        h1 {
            font-size: 24px;
            color: #007bff;
            margin-bottom: 20px;
        }

        p {
            font-size: 16px;
            color: #666;
            margin-bottom: 10px;
        }

        ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        li {
            margin-bottom: 5px;
        }

        .emphasis {
            font-weight: bold;
            color: #007bff;
        }

        .signature {
            margin-top: 20px;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Endorsement Request Notification</h1>
        <p>Dear [Recipient Name],</p>
        <p>A new endorsement request has been submitted for the scheme with ID: <span
                class="emphasis">{{ $scheme_id }}</span>.</p>
        <p>The request ID is: <span class="emphasis">{{ $request_id }}</span>.</p>
        <p>Type of Endorsement: <span class="emphasis">{{ $endorsement_type }}</span>.</p>
        <p>Effective Date: <span class="emphasis">{{ $effective_date }}</span>.</p>
        <p>Requested Change: <span class="emphasis">{{ $requested_change }}</span>.</p>
        <p>Please take necessary action regarding this endorsement request.</p>
        <p>If you have any questions or need further assistance, feel free to reach out.</p>
        <p>Thank you.</p>
        <div class="signature">
            <p>Best Regards,</p>
            <p>Your Company Name</p>
        </div>
    </div>
</body>

</html>
