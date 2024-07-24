<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Request Notification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            /* background-color: #282d38; */
            /* Light blue background color */
        }

        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #fff;
            /* White container background color */
            box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1);
            /* Mild box shadow */
        }

        h1 {
            font-size: 24px;
            color: #007bff;
            /* Blue color for heading */
            margin-bottom: 20px;
            /* Increased margin bottom */
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
            /* Make text bold for emphasis */
            color: #007bff;
            /* Blue color for emphasis */
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
        <h1>New Claims Request Notification</h1>
        <p>Dear {{ $recipientName }},</p>
        <p>A new claim request has been submitted for the scheme with ID: <span
                class="emphasis">{{ $schemeId }}</span>.</p>
        <p>The claim request number is: <span class="emphasis">{{ $claimRequestNumber }}</span>.</p>
        <!-- Include other dynamic content here -->
        <p>Please take necessary action regarding this claim request.</p>
        <p>If you have any questions or need further assistance, feel free to reach out.</p>
        <p>Thank you.</p>
        <div class="signature">
            <p>Best Regards,</p>
            <p>BRITAM</p>
        </div>
    </div>
</body>

</html>
