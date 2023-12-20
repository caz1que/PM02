# Исчерпывающее руководство по выполнению профессионального модуля ПМ.02 "Организация сетевого администрирования"

<p align="center">
  <img src="https://github.com/caz1que/PM02/assets/104842811/5950ccae-46ad-44f0-8e93-f51a3217a46f)https://github.com/caz1que/PM02/assets/104842811/5950ccae-46ad-44f0-8e93-f51a3217a46f" style="height:400px; width:1000px;"/>
</p>

<br/>

## Перед началом работы

Используемый дистрибутив на всех ВМ: **Debian 10**

Выделенное количество ОЗУ:

- на SRV1: **2ГБ**
- на SRV2: **4ГБ**
- на CLI1: **4ГБ** (можно и 2ГБ)

IP-адресация, хостнеймы, типы и количество адаптеров - согласно заданию (таблица адресации в конце). Для SRV1 рекомендую использовать NAT, т.к. IP-адрес не слетит при смене сети.

<br/>

## Настройка DHCP-сервера


Для настройки DHCP-сервера на SRV1 скачиваем пакет **isc-dhcp-server**. В ходе установки он будет ругаться что нихуя не работает - но нас это ебать не должно, работаем дальше:

```
root@SRV1:/home/ivan# apt install isc-dhcp-server
````

На всякий случай делаем бэкап конфигурационного файла:

```
root@SRV1:/home/ivan# cp /etc/dhcp/dhcpd.conf{,.backup}
root@SRV1:/home/ivan# cat /dev/null > /etc/dhcp/dhcpd.conf
```

Открываем файл **/etc/dhcp/dhcpd.conf** и создаем новую подсеть для раздачи:

```
subnet 192.168.2.0 netmask 255.255.255.0 {
 range 192.168.2.144 192.168.2.254;
 option subnet-mask 255.255.255.0;
 option broadcast-address 192.168.2.255;
 option domain-name-servers 172.16.2.2, 8.8.8.8, 8.8.8.4;
 option routers 192.168.2.1;
 default-lease-time 7200;
 max-lease-time 480000;
}
```

- **range 192.168.X.144 192.168.X.254;** - диапазон выдаваемых адресов (их тут 110, как требуется по заданию, делаем также как я, просто меняем третий октет)
- **option domain-name-servers 172.16.X.2, 8.8.8.8, 8.8.8.4;** - IP-адрес будущего туннельного интерфейса DNS-сервера (SRV2), и IP-адреса классических DNS-серверов (чтобы у SRV2 и CLI1 был нормальный выход в Интернет). Пока мы туннель и DNS не настроили, но все равно пишем как я.
- **option routers 192.168.X.1;** - шлюз, который будет выдаваться клиентам. Это адрес интерфейса SRV1 с LAN-сегментом "lan".

Остальное если интересно - загуглите. Просто копируем и меняем айпишники под себя.


Далее открываем файл **/etc/default/isc-dhcp-server** и прописываем в параметре INTERFACESv4 название интерфейса с типом LAN сегмент "lan".

```
# On what interfaces should the DHCP server (dhcpd) serve DHCP requests?
#      Separate multiple interfaces with spaces, e.g. "eth0 eth1".
INTERFACESv4="ens37"
INTERFACESv6=""
```


Перезагружаем isc-dhcp-server для принятия изменений.

```
root@SRV1:/home/ivan# systemctl restart isc-dhcp-server
```


Проверяем на CLI1. Если не прилетело - попробуйте в настройках включить-выключить проводное соединение (напоминаю, что CLI1 должен быть с графикой, и там не используется классический /etc/network/interfaces).

```
root@CLI1:/home/ivan# ip a
```


Перевыдать адресацию по DHCP можно следующим образом:

```
root@CLI1:/home/ivan# sudo dhclient -v
```

<br/>

## Настройка перегруженного NAT (маскарадинг)


Для того, чтобы узлы SRV2 и CLI1 имели выход в Интернет и на них можно было потом качать пакеты, именно сейчас самое время настроить NAT.

На SRV1 включаем пересылку пакетов:

```
root@SRV1:/home/ivan# nano /etc/sysctl.conf
```


Далее расскоментим строчку net.ipv4.ip_forward=1. **Для принятия изменений надо перезагрузить SRV1.**

```
# Uncomment the next line to enable packet forwarding for IPv4
net.ipv4.ip_forward=1
```


Затем, при помощи **iptables** создаем правило для NAT для маскарадинга:

```
root@SRV1:/home/ivan# sudo iptables -t nat -A POSTROUTING -o ens33 -j MASQUERADE
```

, где ens33 - интерфейс с типом NAT.


Проверяем доступ в Интернет классическим образом - ping 8.8.8.8 (на SRV2 и CLI1).

Теперь следующая проблема. **После перезагрузки ОС все правила слетят.** Поэтому рекомендую сделать следующее: сохранить правила в отдельный файл, и сделать так, чтобы они автоматически запускались при помощи скрипта вместе с запуском ОС.


Создаем директорию **/etc/iptables-conf** и при помощи утилиты **iptables-save** сохраняем правила в отдельный файл в этой директории:

```
root@SRV1:/home/ivan# sudo mkdir /etc/iptables-conf
root@SRV1:/home/ivan# sudo iptables-save -f /etc/iptables-conf/iptables_rules.ipv4
```


Создаем в директории **/etc/network/if-pre-up.d** файл **iptables**:

```
root@SRV1:/home/ivan# sudo touch /etc/network/if-pre-up.d/iptables
root@SRV1:/home/ivan# sudo nano /etc/network/if-pre-up.d/iptables
```


В самом файле пишем:

```
#!/bin/sh
/sbin/iptables-restore < /etc/iptables-conf/iptables_rules.ipv4
```


Затем, делаем файл исполняемым (чтобы мог запускаться вместе с ОС):

```
root@SRV1:/home/ivan# sudo chmod +x /etc/network/if-pre-up.d/iptables
```


На этом настройка перегруженного NAT окончена, поздравляю, теперь вы можете выходить со своими вопросами в тырнет

<br/>

## Настройка VPN-соединения (GRE-туннеля)


На самом деле, VPN-соединение - это громко сказано. Поэтому не надо обсираться со страху. Нахуя тут GRE-туннель между двумя напрямую соединенными узлами - одному лишь Богу известно. Но мы все равно сделаем, так надо.


Прежде всего на обеих машинах (SRV1 и SRV2) нужно открыть файлик **/etc/modules** и прописать в конце строчку - **ip_gre**. Делается это для того, чтобы при запуске ОС у вас сразу подгружался этот модуль ядра.

```
# /etc/modules: kernel modules to load at boot time.
#
# This file contains the names of kernel modules that should be loaded
# at boot time, one per line. Lines beginning with "#" are ignored.

ip_gre
```


Теперь подгружаем сам модуль ядра этой командой (на обеих машинах):

```
root@SRV1:/home/ivan# sudo modprobe ip_gre
```


Затем открываем **/etc/network/interfaces** и создаем новый интерфейс.

На SRV1:

```
auto tun100
iface tun100 inet tunnel
       address 172.16.2.1
       netmask 255.255.255.240
       mode gre
       local 10.10.2.1
       endpoint 10.10.2.2
       ttl 255
```


На SRV2:

```
auto tun100
iface tun100 inet tunnel
       address 172.16.2.2
       netmask 255.255.255.240
       mode gre
       local 10.10.2.2
       endpoint 10.10.2.1
       ttl 255
```

, где:

- **local** - IP-адрес интерфейса с LAN сегментом "srv" на машине, на которой настраиваем туннель
- **endpoint** - IP-адрес интерфейса с LAN сегментом "srv" на машине, к которой мы хотим настроить туннель


Для принятия изменений перезагружаем networking:

```
root@SRV1:/home/ivan# systemctl restart networking
```


Проверка - пинг IP-адреса туннеля соседней машины.

<br/>

## Настройка DNS-сервера


А вот на этом пункте внимательно - даже я столкнулся с **неведомой хуйней** на этом этапе. Поэтому прежде чем мне писать и ебать мозги, убедитесь лишний раз, что все делали точно по методичке.

Скачиваем на SRV2 пакет **bind9**:

```
root@SRV2:/home/ivan# apt install bind9 -y
```


После установки открываем файл конфига bind9 - **/etc/bind/named.conf.options** и заменяем все содержимое на следующее **(ЕСЛИ КОПИРУЕТЕ - ПРОБЕЛЫ ЗАМЕНЯЕТЕ НА ТАБЫ)**:

```
options {
       directory "/var/cache/bind";
       dnssec-validation auto;
       listen-on { 172.16.2.2; };
       listen-on-v6 { any; };
};
```

, где  **listen-on { 172.16.2.2; };** - IP-адрес тоннельного интерфейса SRV2.


Затем открываем файл **/etc/bind/named.conf.local** и создаем там прямую зону **(ЕСЛИ КОПИРУЕТЕ - ПРОБЕЛЫ ЗАМЕНЯЕТЕ НА ТАБЫ)**:

```
zone "mpt-01-02.xyz" {
       type master;
       file "/var/dns/db.mpt-01-02.xyz";
       allow-transfer { 172.16.2.2; };
};
```


Создаем директорию **/var/dns** и копируем в эту директорию файл прямой зоны **db.local** с именем **db.mpt-01-XX.xyz**, где XX - номер по журналу (если номер не двухзначный - нолик в начале):

```
root@SRV2:/home/ivan# mkdir /var/dns
root@SRV2:/home/ivan# cp /etc/bind/db.local /var/dns/db.mpt-01-02.xyz
```


На всякий случай выдадим соответствующие права на новую директорию с файлом зоны в ней:

```
root@SRV2:/home/ivan# chown -R bind:bind /var/dns
root@SRV2:/home/ivan# chmod -R 755 /var/dns
```


Теперь подгоняем под себя файл **/var/dns/db.mpt-01-XX.xyz.** Меняем запись SOA, заменив localhost-ы на SRV1.mpt-01-XX.xyz. и root.mpt-01-XX.xyz. Также создаем 3 А записи - для SRV1, SRV2, и самого доменна.

В качестве адресов используем следующие:
- **SRV1.mpt-01-XX.xyz** - 172.16.X.1
- **SRV2.mpt-01-XX.xyz** - 172.16.X.2
- **mpt-01-XX.xyz** - 192.168.X.1

```
;
; BIND data file for local loopback interface
;
$TTL   604800
@      IN     SOA    SRV1.mpt-01-02.xyz. root.mpt-01-02.xyz. (
                             3        ; Serial
                        604800        ; Refresh
                         86400        ; Retry
                       2419200        ; Expire
                        604800 )      ; Negative Cache TTL
;
@      IN     NS     SRV1.
SRV1.mpt-01-02.xyz.     IN     A      172.16.2.1
SRV2.mpt-01-02.xyz.     IN     A      172.16.2.2
mpt-01-02.xyz. IN     A      192.168.2.1
```

</br>

---

А теперь обещанная неведомая хуйня. Есть такая штука - Apparmor. Что-то типа Центра безопасности Windows, который отвечает за приложения, но только в Linux. Из-за этой залупы у меня ничего не работало. Поэтому делаем следующее: открываем файл **/etc/apparmor.d/usr.sbin.named** и в конце файла (перед скобкой) пишем строку `/var/dns/** rw,`:

```
...
# Site-specific additions and overrides. See local/README for details.
 #include <local/usr.sbin.named>

 /var/dns/** rw,
}
```

Если это не помогает, есть еще один вариант: выключить Apparmor к хуям собачьим. **После нижеприведенных двух команд надо перезагрузить ОС.**

```
root@SRV2:/home/ivan# systemctl stop apparmor
root@SRV2:/home/ivan# systemctl disable apparmor
```

</br>

---


Для проверки настройки файла зоны можно использовать команду **named-checkzone**:

```
root@SRV2:/home/ivan# sudo named-checkzone mpt-01-02.xyz /var/dns/db.mpt-01-02.xyz
```

Для проверки файлов конфигурации можно использовать команду **named-checkconf**:

```
root@SRV2:/home/ivan# sudo named-checkconf /etc/bind/named.conf.local
```

Логи bind9 обычно хранятся по пути /var/log/syslog, в случае чего можно и их чекнуть:

```
root@SRV2:/home/ivan# cat /var/log/syslog
```

---

</br>

Для проверки работы DNS на CLI1 надо скачать пакет **dnsutils** (в него входит утилита nslookup):

```
root@CLI1:/home/ivan# apt install dnsutils
```


Проверяем отправкой запроса через **nslookup** на адрес mpt-01-XX.xyz (в конце команды можно указать IP-адрес сервера, чтобы запрос шел именно ему):

```
root@CLI1:/home/ivan# nslookup mpt-01-02.xyz 172.16.2.2
Server:         172.16.2.2
Address:        172.16.2.2#53

Name:   mpt-01-02.xyz
Address: 192.168.2.1
```

</br>

---

</br>

Также можно настроить использование DNS-сервера на SRV1 (не знаю, насколько это обязательно по заданию).

Скачиваем пакет **resolvconf**:

```
root@SRV1:/home/ivan# apt install resolvconf
```


Стартуем службу resolvconf:

```
root@SRV1:/home/ivan# systemctl start resolvconf
root@SRV1:/home/ivan# systemctl enable resolvconf
```


Редактируем файл **/etc/resolvconf/resolv.conf.d/head** и пишем там адреса DNS-серверов, включая сервер SRV2 (лучше написать его в первой строчке):

```
nameserver 172.16.2.2
nameserver 8.8.8.8
nameserver 8.8.8.4
```


После этого **перезагружаем ОС** для принятия изменений.

Проверяем наличие IP-адресов DNS-серверов в файле /etc/resolv.conf командой:

```
root@SRV1:/home/ivan# cat /etc/resolv.conf
# Dynamic resolv.conf(5) file for glibc resolver(3) generated by resolvconf(8)
#     DO NOT EDIT THIS FILE BY HAND -- YOUR CHANGES WILL BE OVERWRITTEN

nameserver 172.16.2.2
nameserver 8.8.8.8
nameserver 8.8.8.4
nameserver 192.168.201.2
search localdomain
```


Затем проверяем как и на CLI1 работу DNS-сервера на SRV2:

```
root@CLI1:/home/ivan# nslookup mpt-01-02.xyz 172.16.2.2
Server:         172.16.2.2
Address:        172.16.2.2#53

Name:   mpt-01-02.xyz
Address: 192.168.2.1

```

<br/>

## Настройка центра сертификации


Почему я рекомендую сделать ЦС именно сейчас - в дальнейшем, наши веб-ресурсы (Apache2 и Nginx) должны будут работать только по **https**. Поэтому заранее подготовим себе сертификаты.

Есть два варианта создания ЦС:
- при помощи пакета **easy-rsa**
- при помощи встроенной утилиты **openssl**

В рамках данного ПМ вы не ограничены использованием либо первого, либо второго варианта. Я буду делать через openssl, вы же можете сделать через easy-rsa (как в лабах мы делали), главное, чтобы на выходе у вас получилось два файла - **сертификат сервера и закрытый ключ сервера**.

Заранее создадим директорию **/tmp/keys** для хранения сертификатов и ключей, затем в нее перейдем.

```
root@SRV2:/home/ivan# mkdir /tmp/keys
root@SRV2:/home/ivan# cd /tmp/keys
root@SRV2:/tmp/keys#
```


Создаем закрытый ключ для ЦС при помощи **openssl** (алгоритм - RSA, длина ключа - 2048 бит):

```
root@SRV2:/tmp/keys# openssl genrsa -out ca-private-key.pem 2048
```


Создаем самоподписанный сертификат для ЦС. После ввода команды вам предложат ввести данные сертификата, **БУДЬТЕ ВНИМАТЕЛЬНЫ**, Common Name должен быть такой же как и хостнейм машины (SRV2):

```
root@SRV2:/tmp/keys# openssl req -new -x509 -key ca-private-key.pem -out ca-certificate.pem
Country Name (2 letter code) [AU]:RU
State or Province Name (full name) [Some-State]:Moscow
Locality Name (eg, city) []:Troparevo
Organization Name (eg, company) [Internet Widgits Pty Ltd]:MPT
Organizational Unit Name (eg, section) []:SA
Common Name (e.g. server FQDN or YOUR name) []:SRV2
Email Address []:sa50_i.o.artemov@mpt.ru
```


Создаем еще один приватный ключ RSA, на этот раз для сервера.

```
root@SRV2:/tmp/keys# openssl genrsa -out server-private-key.pem 2048
```


Создаем запрос на сертификат (CSR). Вас также попросят ввести данные для сертификата, пишем все также, как и в прошлом сертификате, только **Common Name на этот раз - SRV1** (в конце еще попросят Challenge password и Optional company name - на этих пунктах просто скипаем и жмем enter):

```
root@SRV2:/tmp/keys# openssl req -new -key server-private-key.pem -out server-csr.pem
Country Name (2 letter code) [AU]:RU
State or Province Name (full name) [Some-State]:Moscow
Locality Name (eg, city) []:Troparevo
Organization Name (eg, company) [Internet Widgits Pty Ltd]:MPT
Organizational Unit Name (eg, section) []:SA
Common Name (e.g. server FQDN or YOUR name) []:SRV1
Email Address []:sa50_i.o.artemov@mpt.ru
```


Для хранения серийных номеров сертификатов создадим файл ca-certificate.srl следующим образом:

```
root@SRV2:/tmp/keys# echo 01 > ca-certificate.srl
```


Создаем сертификат сервера:

```
root@SRV2:/tmp/keys# openssl x509 -req -in server-csr.pem -CA ca-certificate.pem -CAkey ca-private-key.pem -out server-certificate.pem
```


По итогу содержимое директории /tmp/keys должно выглядеть вот так:

```
root@SRV2:/tmp/keys# ls -la
итого 32
drwxr-xr-x 2 root root 4096 дек 14 23:28 .
drwxrwxrwt 9 root root 4096 дек 14 23:26 ..
-rw-r--r-- 1 root root 1419 дек 14 23:26 ca-certificate.pem
-rw-r--r-- 1 root root   3 дек 14 23:28 ca-certificate.srl
-rw------- 1 root root 1675 дек 14 23:26 ca-private-key.pem
-rw-r--r-- 1 root root 1273 дек 14 23:28 server-certificate.pem
-rw-r--r-- 1 root root 1045 дек 14 23:28 server-csr.pem
-rw------- 1 root root 1679 дек 14 23:27 server-private-key.pem
```


Теперь нужно закинуть два файла: **server-certificate.pem** и **server-private-key.pem** на SRV1. Сделать это можно при помощи SFTP.

Подрубаемся по SFTP с SRV2 на SRV1:

```
root@SRV2:/tmp/keys# sftp ivan@172.16.2.2
ivan@172.16.2.1's password:
Connected to ivan@172.16.2.1.
sftp>
```


Загружаем файлы на SRV1 в домашнюю директорию пользователя при помощи команды put:

```
sftp> put /tmp/keys/server-certificate.pem /home/ivan/server-certificate.pem

sftp> put /tmp/keys/server-private-key.pem /home/ivan/server-private-key.pem
```


Залетаем на **SRV1** и проверяем, все ли файлы на месте.

```
root@SRV1:/home/ivan# ls -la | grep server
-rw-r--r-- 1 ivan ivan  1273 дек 14 23:35 server-certificate.pem
-rw------- 1 ivan ivan  1679 дек 14 23:36 server-private-key.pem
```

<br/>

## Установка Apache2 & Wordpress


Перед выполнением нижеописанных действий нужно прояснить ситуацию: *я не ебу, что именно по итогу хотел видеть создатель задания*.

Для начала, важно понимать, что такое Wordpress и как он вообще работает:
- **Wordpress** - это по сути своей движок, при помощи которого вы сможете в красивом удобном веб-интерфейсе создать сайт, затем этот сайт Wordpress будет выводить на айпишнике веб-сервера.
- Компоненты, необходимые для работы Wordpress: **веб-сервер** (Apache2 или Nginx), **компилятор PHP** (потому что Wordpress написан на php), **база данных** (для хранения данных).
- Сам Wordpress можно поделить на две части: **админская панель и сайт**. К обеим этим частям можно получить доступ через браузер. Через админскую панель мы создаем и редактируем сайт с его страницами.

Теперь по заданию: то, что от нас требуют использование PHP - еще не значит, что нужно писать код (мы че программисты чтоле). Wordpress по умолчанию требует наличия интерпретатора PHP, а весь код находится уже внутри Wordpress. Поэтому, забиваем на это требование хуй.

---
<br/>

Работать Wordpress будет на Apache2. Поэтому скачиваем его, интерпретатор php-fpm и остальные необходимые пакеты:

```
root@SRV1:/home/ivan# sudo apt install apache2 php-fpm php-curl php-gd php-mbstring php-xml php-xmlrpc php-soap php-intl php-bcmath php-imagick php-mysql php-zip libapache2-mod-php -y
```

<br/>

>Примечание. Если вы решили сразу скачать apache2 и nginx (то есть не как я, устанавливать nginx только после настройки apache2) - у вас может возникнуть конфликт портов (и nginx, и apache2, хотят работать на 80 порту), и какая-либо из служб не будет работать. Поэтому до момента настройки nginx, просто выключите его: systemctl stop nginx

<br/>

Открываем mariadb для создания БД Wordpress.

```
root@SRV1:/home/ivan# sudo mariadb
```


Создаем БД и пользователя с привилегиями для Wordress:

```
mariadb> CREATE USER 'wordpress_user'@'localhost' IDENTIFIED BY 'password';
mariadb> CREATE DATABASE wordpress DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
mariadb> GRANT ALL ON wordpress.* TO 'wordpress_user'@'localhost' IDENTIFIED BY 'password';
mariadb> FLUSH PRIVILEGES;
mariadb> EXIT;
```


Скачиваем архив с Wordpress при помощи **curl** в директории /tmp. Если curl нету - скачиваем (apt install curl).

```
root@SRV1:/home/ivan# cd /tmp
root@SRV1:/tmp# curl -O https://wordpress.org/latest.tar.gz
```


Распаковываем архив при помощи **tar**:

```
root@SRV1:/tmp# tar xzvf latest.tar.gz
```


Создаем файл .htaccess:

```
root@SRV1:/tmp# touch /tmp/wordpress/.htaccess
```


Копируем базовый файл конфигурации Wordpress, создаем директорию upgrade и затем всю директорию wordpress копируем по пути **/var/www/wordpress:**

```
root@SRV1:/home/ivan# cp /tmp/wordpress/wp-config-sample.php /tmp/wordpress/wp-config.php
root@SRV1:/home/ivan# mkdir /tmp/wordpress/wp-content/upgrade
root@SRV1:/home/ivan# sudo cp -a /tmp/wordpress/. /var/www/wordpress
```


Выдаем нужные права на директории Wordpress:

```
root@SRV1:/home/ivan# sudo chown -R www-data:www-data /var/www/wordpress
root@SRV1:/home/ivan# sudo find /var/www/wordpress/ -type d -exec chmod 750 {} \;
root@SRV1:/home/ivan# sudo find /var/www/wordpress/ -type f -exec chmod 640 {} \;
```


Теперь внимательно. Нужно сделать секретные ключи Wordpress и вставить их в конфигурационный файл **/var/www/wordpress/wp-config.php.** Объсняю как это делать.

Сначала генерируем ключи при помощи вот такого вот curl-запроса:

```
root@SRV1:/home/ivan# curl -s https://api.wordpress.org/secret-key/1.1/salt/
```


Вывод команды будет примерно вот таким (**отличаться будут сами ключи, копировать мои ключи нельзя!**):

```
define('AUTH_KEY',        'Br|;jl-BCm kcyb-o~Qy;pu8hGUZ@DXMP>9Wo[Ekv&:F1>YTjBP0PCP([Ms+:Uei');
define('SECURE_AUTH_KEY', '-5+bWi[+G5&&]v32CaGo]u<F0wfr?:/k6/<T&Zib4oP<Mefm!~=mNtvm~!o(a6kb');
define('LOGGED_IN_KEY',   'NZa)F/C>/cre+^jI?D1-f6GW%v|S$j~;Xg>%ju(~<~tOf@/Tl~f|}(O57E-b3eKp');
define('NONCE_KEY',       '$1;U:-TH}h1~1Fa:GanT]GlW-Hut!QU}4!7n_e}O))+3OzV3wFEcu)s!`qI>!{W<');
define('AUTH_SALT',       '-n}=e;O@}1csk1~&E@D`7/VU*C^tRKU9y]Moj<zE7iM@}cUN*H*eZ/bqVWfbT_{u');
define('SECURE_AUTH_SALT', 'Towha]z4O}++%l~!}7d{,w+i -|<q~^CkrE0QlDuaso{s{%h;M/aUKUd+TfVwqPE');
define('LOGGED_IN_SALT',  'e(z||%>z!6j9bK<%L[^8zj9+i:dD-I`)rCeC-j2U}B`[drPR2p|j|Akx]_~rH+E8');
define('NONCE_SALT',      'O;24Cl/ZiEo:;uc,>/8Juevcr}a@8>C+TIV7iec]!(6&*`r]^{(hkrH-<^1+JwGI');
```


Копируем получившуюся ахинею и заменяем вот эту часть конфига в файле **/var/www/wordpress/wp-config.php** на то, то что скопировали из вывода команды:

```
...
define('AUTH_KEY',         'put your unique phrase here');
define('SECURE_AUTH_KEY',  'put your unique phrase here');
define('LOGGED_IN_KEY',    'put your unique phrase here');
define('NONCE_KEY',        'put your unique phrase here');
define('AUTH_SALT',        'put your unique phrase here');
define('SECURE_AUTH_SALT', 'put your unique phrase here');
define('LOGGED_IN_SALT',   'put your unique phrase here');
define('NONCE_SALT',       'put your unique phrase here');
...
```


Теперь нужно указать данные от ранее созданной БД. В том же файле **/var/www/wordpress/wp-config.php** ищем строчки и пишем как у меня:

```
...
define('DB_NAME', 'wordpress');

/** MySQL database username */
define('DB_USER', 'wordpress_user');

/** MySQL database password */
define('DB_PASSWORD', 'password');
...
```


В конце файла **/var/www/wordpress/wp-config.php** надо еще добавить две строчки с указанием https ссылки на IP-адрес (NAT). Кстати, рекомендую при настройке Wordpress использовать только один IP-адрес, и именно тот, где интерфейс NAT, чтобы вы могли потом сразу и легко получить доступ к админской панели прямо с хостового браузера.

```
...
define('WPSITEURL','https://192.168.201.143/');
define('WPHOME','https://192.168.201.143/');
```


Переходим к настройке Apache2. Открываем и редактируем файл **/etc/apache2/ports.conf**.

Так как Wordpress будет работать только по HTTPS, открываем только 443 порт. Открыл я его только на одном IP-адресе машины (NAT).

```
# If you just change the port or add more ports here, you will likely also
# have to change the VirtualHost statement in
# /etc/apache2/sites-enabled/000-default.conf

<IfModule ssl_module>
        Listen 192.168.201.143:443
</IfModule>

<IfModule mod_gnutls.c>
        Listen 192.168.201.143:443
</IfModule>

# vim: syntax=apache ts=4 sw=4 sts=4 sr noet

```


Создаем файл сайта Wordpress для Apache2:

```
root@SRV1:/home/ivan# touch /etc/apache2/sites-available/wordpress.conf
```


Создаем сам файл сайта. Делаем сразу на HTTPS. Снова используем только один айпишник (с интерфейса NAT). Указываем также пути к файлам с сертификатом и ключом сервера, которые мы создали и загрузили на SRV1 ранее в этапе с настройкой ЦС.

```
<VirtualHost 192.168.201.143:443>

    ServerName 192.168.201.143
    DocumentRoot /var/www/wordpress

    SSLEngine on
    SSLCertificateFile /home/ivan/server-certificate.pem
    SSLCertificateKeyFile /home/ivan/server-private-key.pem

    <FilesMatch "\.(cgi|shtml|phtml|php)$">
        SSLOptions +StdEnvVars
    </FilesMatch>

    <Directory /var/www/wordpress>
        Options FollowSymLinks
        AllowOverride Limit Options FileInfo
        DirectoryIndex index.php
        Require all granted
    </Directory>

</VirtualHost>
```


По умолчанию в Apache2 включен сайт с его приветственной страницей (000-default). Нужно его выключить при помощи команды **a2dissite:**

```
root@SRV1:/home/ivan# sudo a2dissite 000-default
```


Включаем модули rewrite и ssl при помощи команды **a2enmod**.

```
root@SRV1:/home/ivan# sudo a2enmod rewrite
root@SRV1:/home/ivan# sudo a2enmod ssl
```


Включаем сайт с Wordpress при помощи команды **a2ensite**:

```
root@SRV1:/home/ivan# sudo a2ensite wordpress
```


Перезагружаем службу apache2:

```
root@SRV1:/home/ivan# systemctl restart apache2
```


Теперь можно попробовать перейти по IP-адресу, где NAT (с использованием https) в браузере вашей хостовой ОС. Сразу предупреждаю, Wordpress может прогрузиться не сразу.

Не забудьте также либо сделать исключение для сайта в браузере (браузер будет ругаться на сертификат), либо сделать доверительные отношения с ЦС (тип их установки будет зависеть от браузера).

Если страница не открывается, можно попробовать перейти по:
- https://ip-где-nat/wp-admin
- https://ip-где-nat/wp-login

Если у вас все-таки получилось зайти в Wordpress, то поздравляю. Теперь можно создать сайт и аккаунт админа. Название сайта рекомендую сделать - mpt-01-XX.xyz

![19a5d4de8c9ebf9e1702f](https://github.com/caz1que/PM02/assets/104842811/38c11d14-3246-4f6e-beb6-8af883c78b2e)


После того, как вы попали в админскую панель Wordpress - идем делать свою страницу (потому что базовую страницу сайта хуй изменишь). Перед этим можно удалить те страницы, которые уже есть (они нам не пригодятся).

![Pasted image 20231216011651](https://github.com/caz1que/PM02/assets/104842811/72c8867d-dd95-481f-822c-4e7e02ac26d3)


В том, как будет выглядеть ваша страница вы ограничены лишь своей фантазией и текущим законодательством РФ.

![Pasted image 20231216011825](https://github.com/caz1que/PM02/assets/104842811/ba4b5f83-43db-425d-b718-4fd6a52920cb)


После того, как вы наигрались со своей страницей, нужно сделать ее основной - т.е., чтобы при переходе на IP-адрес сразу загружалась именно она, а не базовая страница Wordpress.

Для этого в левом меню переходим во "Внешний вид" - "Настроить".

![Pasted image 20231216012017](https://github.com/caz1que/PM02/assets/104842811/5ac87de4-48c8-422d-b044-767d91331030)


Слева выбираем "Настройки главной страницы"

![Pasted image 20231216012038](https://github.com/caz1que/PM02/assets/104842811/0c0d1fe4-0b86-4dea-944f-4614d7200945)


И в качестве домашней страницы выбираем ту, что сделали.

![Pasted image 20231216012147](https://github.com/caz1que/PM02/assets/104842811/51aefdc1-a8b1-4383-acc6-60b0f82d4f3f)


Возвращаемся в терминал. Открываем mariadb и обновляем значения в БД, в которых написан IP-адрес на https://IP-где-NAT

```
root@SRV1:/home/ivan# sudo mariadb
mariadb> use wordpress;
mariadb> update wp_options set option_value='https://192.168.201.143' where option_name='siteurl';
mariadb> update wp_options set option_value='https://192.168.201.143' where option_name='home';
```

**Тестим** - переходим по IP-адресу машины: https://ip-где-NAT. В итоге должна высветиться та страничка, которую мы только что сделали.

<br/>

## Настройка проксирования на Nginx


Использовать на этот раз мы будем локальный IP-адрес (тот, который висит на интерфейсе, где тип адаптера "Внутренняя сеть" и сегмент "lan"). Т.е., 192.168.Х.1.

Для Nginx также будет необходимо сделать HTTPS-подключение.

Если вы спросите, почему мы используем HTTPS и на Apache2, и на Nginx, я отвечу - при попытке проксировать HTTPS-запросы с Nginx на Apache2, работающем на HTTP, у меня возникали ошибки (циклическое перенаправление (301), ошибки доступа к CSS-стилям и изображениям, и т.д. Короче настрадался я знатно). А при работающем HTTPS и на Nginx, и на Apache2 - все работает нормально.

Если вы еще не скачали nginx, самое время это сделать:

```
root@SRV1:/home/ivan# apt install nginx
```


Открываем файл конфигурации **/etc/nginx/nginx.conf** и пишем следующее. IP-адрес в параметрах "proxy_pass" и "proxy_set_header Host" - тот, который на адаптере NAT (там щас должен Wordpress работать у вас). Также указываем пути к сертификату и ключу.

```
events {
        worker_connections 768;
}

http {
        server {
                listen 192.168.2.1:443 ssl;
                server_name mpt-01-02.xyz;

                ssl on;
                ssl_certificate /home/ivan/server-certificate.pem;
                ssl_certificate_key /home/ivan/server-private-key.pem;

                location / {
                proxy_pass https://192.168.201.143/;
                proxy_set_header Host 192.168.201.143;
                proxy_set_header X-Real-IP $remote_addr;
                proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
                proxy_set_header X-Forwarded-Proto $scheme;
                }
        }

        access_log /var/log/nginx/access.log;
        error_log /var/log/nginx/error.log;

}
```


Перезагружаем службу nginx.

```
root@SRV1:/home/ivan# apt install nginx
```


**Проверяем на CLI1.** Переходим в браузере сначала по адресу https://192.168.X.1, затем можно и по доменному имени - https://mpt-01-XX.xyz. В адресной строке при этом не должно быть никаких признаков NAT-овского айпишника. Все выглядит так, будто Wordpress работает по адресу https://192.168.X.1

![Pasted image 20231216013512](https://github.com/caz1que/PM02/assets/104842811/d63b649b-5dd7-4e72-ac19-8c57eea485db)

<br/>

## Настройка файлового сервера Samba


Скачиваем пакет **samba** на SRV2. В ходе установки Samba предложит сделать какую-то хуйню, жмем "Нет":

```
root@SRV2:/home/ivan# apt install samba
```


Сделаем бэкап конфига samba на всякий случай:

```
root@SRV2:/home/ivan# cp /etc/samba/smb.conf /etc/samba/smb.conf.backup
```


Открываем файл конфигурации Samba **/etc/samba/smb.conf** и находим строчку "interfaces". Меняем ее на это (чтобы Samba работал только на туннельном интерфейсе):

```
...
;  interfaces = tun100
...
```


Крутим после этого в конец файла и создаем три ресурса:

```
[pm_01]
comment = pm_01 for SRV1
path = /tmp/pm_01
browseable = yes
read only = no
guest ok = no
valid users = srv1
hosts allow = 172.16.2.1

[pm_02]
comment = pm_02 for SRV1
path = /tmp/pm_02
browseable = yes
read only = no
guest ok = no
valid users = srv1
hosts allow = 172.16.2.1

[pm_03]
comment = pm_03 for CLI1
path = /tmp/pm_03
browseable = yes
read only = no
guest ok = no
valid users = cli1
hosts allow = 192.168.2.
```

, где:

- **path** - путь к директориям, которые мы после этого создадим
- **hosts allow** - параметр, отвечающий за доступ узлов к ресурсу по их IP-адресу. Обратите внимание, для ресурсов для SRV1 мы просто указываем его туннельный IP-адрес, а для ресурса CLI1 указываем всю его подсеть, в которой он находится (потому что он получает адрес по DHCP и он в любой момент может поменяться).


Создаем пользователей SMB:

```
root@SRV2:/home/ivan# sudo useradd srv1
root@SRV2:/home/ivan# sudo useradd cli1
root@SRV2:/home/ivan# sudo smbpasswd -a srv1
New SMB password:
Retype new SMB password:
root@SRV2:/home/ivan# sudo smbpasswd -a cli1
New SMB password:
Retype new SMB password:
```


Создаем директории pm_01, pm_02, pm_03:

```
root@SRV2:/home/ivan# mkdir /tmp/pm_01
root@SRV2:/home/ivan# mkdir /tmp/pm_02
root@SRV2:/home/ivan# mkdir /tmp/pm_03
```


На всякий случай выдадим права на них _(возможно и без этого будет работать, не проверял)_:

```
root@SRV2:/home/ivan# chown srv1:srv1 /tmp/pm_01
root@SRV2:/home/ivan# chown srv1:srv1 /tmp/pm_02
root@SRV2:/home/ivan# chown cli1:cli1 /tmp/pm_03
```


Перезагружаем **smbd** для принятия изменений:

```
root@SRV2:/home/ivan# systemctl restart smbd
```


Как проверять? Переходим на SRV1 и CLI1 и качаем там пакет **samba-client**.

```
root@SRV1:/home/ivan# apt install samba-client
root@CLI1:/home/ivan# apt install samba-client
```


При помощи команды **smbclient** логинимся и получаем доступ к папке следующим образом:

На SRV1:

```
root@SRV1:/home/ivan# smbclient -U srv1 //172.16.2.2/pm_01
Enter WORKGROUP\srv1's password:
Try "help" to get a list of possible commands.
smb: \> exit
root@SRV1:/home/ivan# smbclient -U srv1 //172.16.2.2/pm_02
Enter WORKGROUP\srv1's password:
Try "help" to get a list of possible commands.
smb: \>
```


На CLI1:

```
root@CLI1:/home/ivan# smbclient -U cli1 //172.17.2.2/pm_03
Enter WORKGROUP\cli1's password:
Try "help" to get a list of possible commands.
smb: \>
```


Обратите внимание, с CLI1 вы не должны получать доступ к ресурсам pm_01 и pm_02, и наоборот, с SRV1 вы не должны получать доступ к pm_03.

Пример ошибки логина за не ту директорию:

```
root@SRV1:/home/ivan# smbclient -U cli1 //172.16.2.2/pm_03
Enter WORKGROUP\cli1's password:
tree connect failed: NT_STATUS_ACCESS_DENIED
```

<br/>

## Настройка мониторинга Zabbix


Импортировать репозиторий Zabbix нужно на всех машинах (**SRV1, SRV2, CLI1**) _(учитывайте, что вся методичка рассчитана на Debian 10, поэтому тут качается пакет именно на эту версию):_

```
sudo wget https://repo.zabbix.com/zabbix/6.0/debian/pool/main/z/zabbix-release/zabbix-release_6.0-4+debian10_all.deb

sudo dpkg -i zabbix-release_6.0-4+debian10_all.deb

apt update
```


На SRV1 после импорта репы Zabbix качаем пакеты **zabbix-sql-scripts и zabbix-agent**. Первый нужен, потому что БД будет находиться именно на SRV1 (согласно заданию), а на SRV2 - сам Zabbix. И SRV2 будет коннектиться к БД на SRV1:

```
root@SRV1:/home/ivan# apt install zabbix-sql-scripts zabbix-agent -y
```


Теперь важный момент. По умолчанию, MySQL/Mariadb работают только на локальных адресах. Чтобы у других узлов в сети мог быть доступ к БД, нужно изменить конфигурацинный файл mariadb по пути - **/etc/mysql/mariadb.conf.d/50-server.cnf.** Делаем это на **SRV1**, находим строчку **bind-address** и пишем туда туннельный адрес:

```
...
# Instead of skip-networking the default is now to listen only on
# localhost which is more compatible and is not less secure.
bind-address           = 172.16.2.1
...
```


Для принятия изменений перезагружаем mariadb:

```
root@SRV1:/home/ivan# systemctl restart mariadb
```


Создаем БД и пользователя для Zabbix на SRV1 **(заметьте, что мы создаем не просто localhost пользователя, как в официальном гайде)**:

```
root@SRV1:/home/ivan# sudo mariadb
mariadb> create database zabbix character set utf8mb4 collate utf8mb4_bin;
mariadb> create user zabbix@'%' identified by 'password';
mariadb> grant all privileges on zabbix.* to zabbix@'%';
mariadb> set global log_bin_trust_function_creators = 1;
mariadb> quit;
```


На **SRV1** вводим команду (обратите внимание, в отличие от команды в официальном гайде Zabbix, тут еще указан IP-адрес сервера БД):

```
root@SRV1:/home/ivan# zcat /usr/share/zabbix-sql-scripts/mysql/server.sql.gz | mysql --default-character-set=utf8mb4 -uzabbix --host=172.16.2.1 -p zabbix 
Enter password: (тут пароль password)
```


Делаем вот это на **SRV1**:

```
root@SRV1:/home/ivan# sudo mariadb
mariadb> set global log_bin_trust_function_creators = 0;
mariadb> exit;
```


Переходим на **SRV2** и качаем необходимые пакеты для Zabbix-сервера:

```
root@SRV2:/home/ivan# apt install zabbix-server-mysql zabbix-frontend-php zabbix-nginx-conf zabbix-sql-scripts zabbix-agent
```


Открываем конфиг **/etc/zabbix/zabbix_server.conf** и меняем строчки **DBHost, DBPassword и DBPort:**

```
...
DBHost=172.16.2.1
...
DBPassword=password
...
DBPort=3306
...
```

, где:

- **DBHost** - туннельный IP-адрес SRV1
- **DBPassword** - пароль от юзера БД, если вы просто копипастили, то он остается таким же - "password"
- **DBPort** - порт СУБД (по умолчанию 3306)



В этом же файле **/etc/zabbix/zabbix_server.conf** расскоменчиваем параметр **AllowUnsupportedDBVersions**, и меняем его значение на 1. Делается это для того, чтобы Zabbix мог работать с нашей уже устаревшей версией mariadb.

```
...
AllowUnsupportedDBVersions=1
...
```


В этом же файле **/etc/zabbix/zabbix_server.conf** на SRV2 расскоменчиваем параметр **"SourceIP"** и пишем там туннельный айпишник. _Объясню, нахуя мы это делаем. Когда Zabbix-агент на CLI1 будет отправлять запросы на адрес 172.16.Х.2, ответ ему будет прилетать с адреса 10.10.Х.2. Из-за этого будет возникать ошибка._

```
...
SourceIP=172.16.2.2
...
```


Также на SRV2 в файле агента **/etc/zabbix/zabbix_agentd.conf** делаем тоже самое _(там тоже есть параметр "SourceIP", и если мы его не поменяем на туннельный айпишник, то агент уже самого сервера работать не будет)_:

```
...
SourceIP=172.16.2.2
...
```


Открываем конфиг **/etc/zabbix/nginx.conf** и расскоментим строчки (имя сервера вроде любое можно сделать):

```
listen         8080;
server_name    mpt-01-02-zabbix.xyz;
```


Перезагружаем **службы Zabbix** и включаем их запуск с ОС:

```
root@SRV2:/home/ivan# systemctl restart zabbix-server zabbix-agent nginx php7.3-fpm
root@SRV2:/home/ivan# systemctl enable zabbix-server zabbix-agent nginx php7.3-fpm
```


Если что-то пошло не так, ошибки можно посмотреть вот так:

```
root@SRV2:/home/ivan# cat /var/log/zabbix/zabbix_server.log | tail -n 15
```


Теперь созревает вопрос, **как нам получить доступ к веб-интерфейсу Zabbix на SRV2?**

Есть два варианта:

- **Первый:** сделать проброс портов на NAT-овском интерфейсе SRV1 (уже заеб, но таким образом вы сможете получить доступ к веб-интерфейсу Zabbix прямо с хостового браузера)
- **Второй:** делать все через браузер на CLI1 (он сразу имеет доступ к адресу 172.16.Х.2)


Я буду делать через второй вариант. Если вам прям сильно хочется иметь доступ к Zabbix прямо с хостового браузера - гуглите "проброс портов в Linux" и делайте редирект с NAT-овского IP-шника SRV1 на 8080 порт туннельного интерфейса SRV2. Но я это делать не буду.

Открываем браузер на CLI1 и пишем в адресной строке IP-адрес с портом: **172.16.X.2:8080**

![7600c3517008c00c1b2e8](https://github.com/caz1que/PM02/assets/104842811/994a56f3-5b1b-4ca8-8871-66c540ba407f)


Доходим до момента с подключением к базе данных. Тут **внимательно.** В параметре "Database host" или как у вас там на русском, наверное "Узел базы данных", указываем IP-адрес туннельного интерфейса SRV1. Пароль от пользователя - "password". Уберите еще галочку с **"Database TLS encryption" (я забыл на скрине)**. Остальное оставляем как есть.

![8ca00bf5c2b0aa7d30811](https://github.com/caz1que/PM02/assets/104842811/8fd416ef-22b4-488b-8697-d0fac8729921)


Остальные шаги установки выполняем по умолчанию.

Перед добавлением узлов скачаем агента Zabbix на CLI1 (на SRV1 вы его должны уже были скачать, а репозиторий Zabbix добавить в самом начале главы на все три машины).

Залетаем на CLI1 и качаем пакет **zabbix-agent**.

```
root@CLI1:/home/ivan# apt install zabbix-agent
```


В конфигурационном файле **/etc/zabbix/zabbix_agentd.conf** находим параметры **"Server" и "ServerActive"** и пишем там адрес туннельного интерфейса SRV2 (**делаем это на всех трех машинах**).

```
...
Server=172.16.2.2
...
ServerActive=172.16.2.2
...
```


Также меняем в этом файле параметр **"Hostname"** на хостнейм машины агента.

На SRV1:

```
...
Hostname=SRV1
...
```
  

На CLI1:

```
...
Hostname=CLI1
...
```

  

На SRV2 можно оставить как есть.

Для принятия изменений перезагружаем **на всех машинах** службу агента:

```
systemctl restart zabbix-agent
```


Затем заходим в веб-интерфейс Zabbix-сервера и ищем "Узлы" или же "Hosts". В углу жмякаем **"Добавить узел" или "Create host"**.

Узел добавляем в группу **"Discovered hosts"**, а подключение выполняем через интерфейс (пишем там айпишник машины агента). Еще выбираем шаблон - **Linux by Zabbix agent**.

![c6d52ec7f96f5dc2ee210](https://github.com/caz1que/PM02/assets/104842811/97c7e91b-a968-4ac1-a45e-95c08201b55b)


Локальный IP-адрес узла Zabbix server можно поменять на туннельный (если его доступность горит красненьким):

![715edd96183613201fb77](https://github.com/caz1que/PM02/assets/104842811/704107e7-b164-47fd-9f06-fa6fb9a93dbd)


Надо подождать, пока показатель "Availability" или "Доступность" будут гореть зелененькими в списке узлов.

Конечный вид "узлов" должен быть такой:

![0e982411d7dcc2c4c2c89](https://github.com/caz1que/PM02/assets/104842811/8e3e71cf-82a9-4b7f-8c56-ac5aa6e13494)

<br/>

## Послесловие


После сего избрал Господь и других семьдесят учеников, и послал их по два пред лицом Своим во всякий город и место, куда Сам хотел идти,

и сказал им: жатвы много, а делателей мало; итак, молите Господина жатвы, чтобы выслал делателей на жатву Свою.

Идите! Я посылаю вас, как агнцев среди волков.

Не берите ни мешка, ни сумы́, ни обуви, и никого на дороге не приветствуйте.

В какой дом войдете, сперва говорите: мир дому сему;

и если будет там сын мира, то почиет на нём мир ваш, а если нет, то к вам возвратится.

В доме же том оставайтесь, ешьте и пейте, что у них есть, ибо трудящийся достоин награды за труды свои; не переходите из дома в дом.

И если придёте в какой город и примут вас, ешьте, что вам предложат,

и исцеляйте находящихся в нём больных, и говорите им: приблизилось к вам Царствие Божие.

Если же придете в какой город и не примут вас, то, выйдя на улицу, скажите:

и прах, прилипший к нам от вашего города, отрясаем вам; однако же знайте, что приблизилось к вам Царствие Божие.
