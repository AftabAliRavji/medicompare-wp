<?php
/**
 * Custom Admin Email Template
 *
 * Variables:
 * {{email_subject}}
 * {{custom_content}}
 */
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>

<body style="
    margin:0;
    padding:0;
    background:#f5f5f5;
    font-family:Arial, Helvetica, sans-serif;
">

    <table
        width="100%"
        cellpadding="0"
        cellspacing="0"
        border="0"
        style="background:#f5f5f5;padding:30px 0;"
    >

        <tr>

            <td align="center">

                <table
                    width="700"
                    cellpadding="0"
                    cellspacing="0"
                    border="0"
                    style="
                        background:#ffffff;
                        border:1px solid #e5e5e5;
                        border-radius:8px;
                        overflow:hidden;
                    "
                >

                    <!-- HEADER -->
                    <tr>
                        <td>

                            <?php

                            $mc_assets =
                                plugin_dir_url(dirname(__FILE__, 2))
                                . 'assets/img/';

                            include dirname(__FILE__, 3)
                                . '/templates/header-pharmacy.php';

                            ?>

                        </td>
                    </tr>

                    <!-- SUBJECT -->
                    <tr>
                        <td style="
                            padding:25px 30px 0 30px;
                        ">

                            <h2 style="
                                margin:0;
                                color:#333333;
                                font-size:22px;
                            ">
                                {{email_subject}}
                            </h2>

                        </td>
                    </tr>

                    <!-- BODY -->
                    <tr>

                        <td
                            style="
                                padding:25px 30px;
                                color:#333333;
                                font-size:15px;
                                line-height:1.7;
                            "
                        >

                            {{custom_content}}

                        </td>

                    </tr>

                    <!-- FOOTER -->
                    <tr>

                        <td
                            style="
                                border-top:1px solid #e5e5e5;
                                padding:30px;
                                background:#fafafa;
                                color:#555555;
                                font-size:14px;
                            "
                        >

                            <p style="
                                margin:0 0 10px 0;
                            ">
                                For further information please visit:
                            </p>

                            <p style="
                                margin:0 0 15px 0;
                            ">
                                <a
                                    href="https://www.sourcemedpharma.com"
                                    target="_blank"
                                    style="
                                        color:#2271b1;
                                        text-decoration:none;
                                    "
                                >
                                    www.sourcemedpharma.com
                                </a>
                            </p>

                            <p style="
                                margin:0 0 15px 0;
                            ">
                                Email:
                                <a
                                    href="mailto:support@sourcemedpharma.com"
                                    style="
                                        color:#2271b1;
                                        text-decoration:none;
                                    "
                                >
                                    support@sourcemedpharma.com
                                </a>
                            </p>

                            <p style="
                                margin:20px 0 5px 0;
                            ">
                                Kind regards,
                            </p>

                            <p style="
                                margin:0;
                            ">
                                <em>
                                    SourceMed Pharma Support Team
                                </em>
                            </p>

                        </td>

                    </tr>

                </table>

            </td>

        </tr>

    </table>

</body>
</html>