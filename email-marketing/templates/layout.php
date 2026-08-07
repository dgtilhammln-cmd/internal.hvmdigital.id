<?php
/* ==========================================================
   TITAN EXECUTIVE V2 - THE SOVEREIGN WHITE
   Modern, Professional, and High-Contrast Design
   ========================================================== */

function get_email_template($subject, $content, $name) {
    // Assets
    $logo_url = "https://internal.hvmdigital.id/uploads/logohvm.png"; 
    $year = date('Y');
    
    return "
    <!DOCTYPE html>
    <html lang='id'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <link href='https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap' rel='stylesheet'>
        <style>
            body { margin: 0; padding: 0; background-color: #f6f9fc; font-family: 'Montserrat', Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; }
            table { border-collapse: collapse !important; }
            .main-card { background-color: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
            .btn-premium {
                text-decoration: none;
                background: linear-gradient(135deg, #a1ff5a 0%, #4efdc4 100%);
                color: #000000 !important;
                font-weight: 800;
                padding: 18px 35px;
                border-radius: 12px;
                display: inline-block;
                letter-spacing: 1px;
                text-transform: uppercase;
                font-size: 13px;
                box-shadow: 0 8px 20px rgba(161, 255, 90, 0.3);
            }
            @media only screen and (max-width: 600px) {
                .container { width: 100% !important; }
                .content-box { padding: 40px 25px !important; }
            }
        </style>
    </head>
    <body style='margin: 0; padding: 0; background-color: #f6f9fc;'>
        <table border='0' cellpadding='0' cellspacing='0' width='100%'>
            <tr>
                <td align='center' style='padding: 40px 15px;'>
                    
                    <table class='container' border='0' cellpadding='0' cellspacing='0' width='600' class='main-card' style='background-color: #ffffff; border-radius: 20px; overflow: hidden;'>
                        
                        <!-- Header Branding Bar (Gives contrast to the white logo) -->
                        <tr>
                            <td align='center' style='padding: 35px; background-color: #050505;'>
                                <img src='$logo_url' alt='HVM' width='180' style='display: block; pointer-events: none;'>
                            </td>
                        </tr>
                        
                        <!-- Content Area -->
                        <tr>
                            <td class='content-box' style='padding: 60px 50px;'>
                                <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                                    <tr>
                                        <td style='color: #000000; font-size: 26px; font-weight: 800; line-height: 1.2; padding-bottom: 30px; letter-spacing: -0.5px;'>
                                            $subject
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style='color: #4a4a4a; font-size: 16px; line-height: 1.8; padding-bottom: 40px;'>
                                            Halo <span style='color: #000; font-weight: 700;'>$name</span>,<br><br>
                                            " . nl2br($content) . "
                                        </td>
                                    </tr>
                                    
                                    <!-- Premium Action Button -->
                                    <tr>
                                        <td align='left'>
                                            <a href='https://hvmdigital.id' class='btn-premium'>
                                                Tinjau Proposal &nbsp; <span style='font-size: 18px;'>→</span>
                                            </a>
                                        </td>
                                    </tr>
                                    
                                    <!-- Signature -->
                                    <tr>
                                        <td style='padding-top: 60px;'>
                                            <table border='0' cellpadding='0' cellspacing='0'>
                                                <tr>
                                                    <td style='width: 3px; background: linear-gradient(to bottom, #a1ff5a, #4efdc4); border-radius: 10px;'></td>
                                                    <td style='padding-left: 20px;'>
                                                        <p style='margin: 0; color: #000; font-size: 16px; font-weight: 700;'>Ilham Maulana</p>
                                                        <p style='margin: 0; color: #999; font-size: 13px; font-weight: 500;'>Business Development - HVM Digital</p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        
                        <!-- Footer Section -->
                        <tr>
                            <td style='padding: 45px 50px; background-color: #fafafa; border-top: 1px solid #f0f0f0;'>
                                <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                                    <tr>
                                        <td style='padding-bottom: 25px; text-align: left;'>
                                            <p style='margin: 0; color: #111; font-size: 13px; font-weight: 700;'>PT. HVM Orbit Studios</p>
                                            <p style='margin: 5px 0 0 0; color: #888; font-size: 12px; line-height: 1.5;'>
                                                Surabaya, Jawa Timur, Indonesia<br>
                                                Official CS: <a href='tel:085179982373' style='color: #000; text-decoration: none; font-weight: 600;'>085179982373</a>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style='border-top: 1px solid #ebebeb; padding-top: 25px; color: #bbb; font-size: 11px; line-height: 1.6;'>
                                            &copy; $year <a href='https://hvmdigital.id' style='color: #bbb; text-decoration: none;'>hvmdigital.id</a>. Seluruh hak cipta dilindungi.<br>
                                            Email ini dikirim secara otomatis oleh sistem kecerdasan buatan Nebula.<br>
                                            <a href='#' style='color: #999; text-decoration: underline;'>Hentikan berlangganan</a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                    
                </td>
            </tr>
        </table>
    </body>
    </html>";
}