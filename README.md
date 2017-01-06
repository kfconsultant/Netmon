
# Netmon
A simple traffic monitor for users based on mac address for raspbery pi/orange pi

This is simple php project that use wireshark cli (tshark) to capture and analyse transform packages and extract mac and traffic from them. This project implemented and tested on `orange pi pc`.

The interface is very simple and need more advance features but the foundation is ready. Maybe I can some extra features in future.
If you know a good php interface for this application please introduce it.

**Install**

 1. This project need to `php` , `apache`, `mysql` , `tcpdump` , `upstart` be installed.
Upstart and tcpdump for Debian/Ubuntu installation described bellow:

        apt install tshark upstart


 2. Now should install service by copying `upstart/netmon.conf` to `/etc/init`.

 3. Put the project files in the `/var/www/html/netmon` 
 
 4. Create  database and tables by importing the `database/import.sql`.

 5. Start service using `service netmon start`. Service runs as
    `www-data` (the apache user).

 6. Now run simple data summery by navigating
    `http://<server-id>/netmon`



For monitoring traffic I enabled ip4_forwarding and use the pi as internet access point. 

[MyComputer] <======> (Orange PI) <======> [Internet Router]

This project inspired from ronenb blog , you can find more helpful manual there :
http://blog.ronenb.com/2016/08/20/network-traffic-analyzer-with-raspberrypi/

Also I used this guide to install wirelless ap on my pi:
https://frillip.com/using-your-raspberry-pi-3-as-a-wifi-access-point-with-hostapd/

![enter image description here](previews/1.png)