<?php
echo '<style>
    .search-box {
        max-width: 600px;
        margin: 20px auto;
        display: flex;
        gap: 10px;
        justify-content: center;
    }
    .search-box input[type="text"] {
        flex-grow: 1;
        padding: 10px;
        font-size: 16px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    .search-box button {
        padding: 15px 30px;
        font-size: 16px;
        background-color: #0073aa;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    .finance-wrapper {
        display: flex;
        justify-content: center;
        gap: 80px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .section-title {
        font-size: 25px;
        font-weight: bold;
        background-color: #dbdbdb;
        padding: 20px;
        border-radius: 6px;
        text-align: left;
        margin: 40px auto 0 auto;
        width: 100%;
        max-width: 100%;
        position: relative;
        padding-left: 50px;
        border: 1px solid #ccc;
    }

    .toggle-btn {
        position: absolute;
        right: 40px;
        top: 8px;
        font-size: 40px;
        background-color: transparent;
        border: none;
        cursor: pointer;
    }

    .section-content {
        display: block;
        margin: 20px auto 40px auto;
        width: 95%;
    }

    .sync-section {
        display: flex;
        justify-content: center;
        gap: 30px;
        margin: 50px auto;
    }

    #sync-sharepoint-btn, #push-recordings-btn {
        font-size: 18px;
        padding: 20px 60px;
        border: none;
        border-radius: 6px;
        background-color: #0073aa;
        color: white;
        cursor: pointer;
        transition: background-color 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.15);
    }

    #sync-sharepoint-btn:hover, #push-recordings-btn:hover {
        background-color: #005c99;
    }

    .generaltable {
        border: 1px solid #ccc;
        border-radius: 8px;
        border-collapse: collapse;
        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        width: 100%;
        table-layout: fixed;
    }

    .generaltable colgroup col:first-child {
        width: 30% !important;
        background-color: #f9f9f9;
    }

    .generaltable colgroup col:last-child {
        width: 70% !important;
    }

    .generaltable th, .generaltable td {
        border: 1.2px solid #bbb;
        padding: 10px;
        word-wrap: break-word;
    }

    .sharepoint-table {
        table-layout: auto;
        width: 70%;
        margin: 0 auto;
    }

    .sharepoint-table th, .sharepoint-table td {
        padding: 8px 12px;
        word-wrap: break-word;
        text-align: center;
    }

    .sharepoint-table th {
        background-color: #0073aa;
        color: white;
        font-weight: bold;
    }

    .sharepoint-table td {
        background-color: #f9f9f9;
        border: 1px solid #ccc;
    }

    .sharepoint-table tr:hover td {
        background-color: #f1f1f1;
    }

    .section-divider {
        border-top: 3px solid #888;
        margin: 80px auto;
        width: 85%;
    }

</style>';
