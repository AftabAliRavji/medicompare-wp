<?php
/**
 * Variables:
 * {{event_type}}
 * {{event_message}}
 * {{pharmacy_name}}
 * {{pharmacy_id}}
 * {{event_time}}
 * {{admin_link}}
 */

$logo_url = plugin_dir_url(dirname(__FILE__, 2)) . 'assets/img/logo.png';

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body style="
    font-family: Arial, sans-serif;
    background:#f5f5f5;
    padding:30px;
">

<div style="
    max-width:700px;
    margin:0 auto;
    background:#ffffff;
    border:1px solid #dddddd;
">

    <div style="
        background:#c62828;
        color:#ffffff;
        padding:20px;
    ">

    <p><img
                                src="<?php echo esc_url($logo_url); ?>"
                                alt="Source Med Pharma"
                                width="360"
                                style="display:block;width:100%;max-width:360px;height:auto;border:0;"
                            >
</p>
        <h2 style="margin:0;">
            GoCardless Alert
        </h2>
    </div>

    <div style="padding:30px;">

        <p>
            <strong>Action Required</strong>
        </p>

        <p>
            A GoCardless event requires attention.
        </p>

        <table cellpadding="8" cellspacing="0">

            <tr>
                <td><strong>Event</strong></td>
                <td>{{event_type}}</td>
            </tr>

            <tr>
                <td><strong>Pharmacy</strong></td>
                <td>{{pharmacy_name}}</td>
            </tr>

            <tr>
                <td><strong>Pharmacy ID</strong></td>
                <td>{{pharmacy_id}}</td>
            </tr>

            <tr>
                <td><strong>Time</strong></td>
                <td>{{event_time}}</td>
            </tr>

        </table>

        <p>
            {{event_message}}
        </p>

        <p>
            The pharmacy account has automatically been restricted.
        </p>

        <p>
            {{admin_link}}
                View Pharmacy Record
            </a>
        </p>

    </div>

</div>

</body>
</html>