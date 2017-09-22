# TotalConnectAPI
IoT - Honeywell TotalConnect Alarm system integration (with OpenHAB, SmartThings, Home Assistant)

Use this script to integrate Honeywell TotalConnect AlarmNet monitoring service to your IoT/Home Automation hub. I am using OpenHAB2, so I have also included the OpenHAB2 integration scripts, however, you should be able to use this proxy with any IoT/Home Automation hub (including SmartThings, Home Assistant etc)

Files:
======
TC2Proxy.php: this is the main brains behind the integration. This PHP script logs into Honeywell TotalConnect system and keeps the connection alive. It serves as a proxy to ARM, DISARM or Check Status of the system.

OpenHab2/http.conf: This file is OpenHab2 file used with Http binding. Use this to create a cache binding that will regularly poll TotalConnect Proxy to update the status of the system in OpenHAB2. This will download the ARM/DISARM status of the system and the status of all the Zones.
OpenHab2/totalconnect.items: This is the item definition in OpenHab2. Use this to define the security system and all the contact zones in the system.
OpenHab2/admin.sitemap: This is the sitemap in OpenHab2. Use this to define a status indicator for the system (ARM/DISARM status), switch to ARM/DISARM the system and a group to display the status of all the zones in the system.

Installation and Configuration
==============================
1. Place TC2Proxy.php in the PHP installation in the Web Server. I have it in /var/www/html but it may vary by your installation.

2. Configure the following variables in TC2Proxy.php
USERNAME: This is your TotalConnect username. Don't use your Master User. Create another account for your Home Automation. 
PASSWD: This is your TotalConnect Passwd.

MYUSERID: This userid needs to be supplied when calling the proxy script. This adds a layer of protection that if anyone to penetrate your network and run the script, the script will not ARM/DISARM the system if you don't supply this username and password in the URL or Web Authentication.
MYPASSWD: This password needs to be passed when calling the script.

3. For OpenHab2 configuration, copy and configure the following scripts.
http.conf: Append contents of this file to /opt/openhab2/conf/services/http.conf
totalconnect.items: Copy this file to /opt/openhab2/conf/items and customize for your zones
admin.sitemap: Append contents of this file to /opt/openhab2/conf/sitemaps/admin.sitemap and customize for your sitemap

Feel free to leave comments here or contact me for any support.
