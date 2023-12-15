# Исчерпывающее руководство по выполнению профессионального модуля ПМ.02 "Организация сетевого администрирования"

<p align="center">
  <img src="https://github.com/caz1que/PM02/assets/104842811/5950ccae-46ad-44f0-8e93-f51a3217a46f)https://github.com/caz1que/PM02/assets/104842811/5950ccae-46ad-44f0-8e93-f51a3217a46f"/>
</p>

## Перед началом работы

Используемый дистрибутив на всех ВМ: **Debian 10**

Выделенное количество ОЗУ:
- на SRV1: **2ГБ**
- на SRV2: **4ГБ**
- на CLI1: **4ГБ** (можно и 2ГБ)

IP-адресация, хостнеймы, типы и количество адаптеров - согласно заданию (таблица адресации в конце). Для SRV1 рекомендую использовать NAT, т.к. IP-адрес не слетит при смене сети.

## Настройка перегруженного NAT (маскарадинг)

Рекомендую забить хуй на порядок действий, который указан в задании. Во избежании путаницы лучше придерживаться порядка действий из моей методички.

Именно поэтому первым делом предлагаю настроить маскарадинг, чтобы узлы SRV2 и CLI1 сразу имели выход в Интернет, и вы могли скачать все необходимые пакеты, которые могут пригодиться в ходе выполнения работы.


Перед этим включим пересылку пакетов на SRV1:

```
root@SRV1:/home/ivan# nano /etc/sysctl.conf
```

Далее расскоментим строчку net.ipv4.ip_forward=1

```
# Uncomment the next line to enable packet forwarding for IPv4
net.ipv4.ip_forward=1
```

**Для принятия изменений надо перезагрузить SRV1**


Затем, при помощи iptables создаем правило для NAT для маскарадинга:

```
root@SRV1:/home/ivan# sudo iptables -t nat -A POSTROUTING -o ens33 -j MASQUERADE
```

, где ens33 - интерфейс с типом NAT.

Проверяем классическим образом - пингом на 8.8.8.8 на SRV2. CLI1 пока не трогаем (проверить можно будет после настройки DHCP).


Теперь следующая проблема. После перезагрузки ОС все правила слетят. Поэтому рекомендую сделать следующее: сохранить правила в отдельный файл, и сделать так, чтобы они автоматически запускались при помощи скрипта вместе с запуском ОС.

