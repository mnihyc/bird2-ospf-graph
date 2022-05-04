#!/bin/bash

# url to update.php in backend
url="https://XXX/backend/update.php"
# token needed for upgradation
token="VER_UPD_TOK"

# sleep to stagger submission time
sleep $((4 + $RANDOM % 10))

# read Router ID
r1=`cat /etc/bird/predef.conf | head -n 1`
r2=${r1#*router id }
rid=${r2%;*}
# read 4 files
str=`printf '%s' "$(cat /etc/bird/ospf.conf)"`
str+=`printf '\x01\x02%s' "$(ip a s)"`
str+=`printf '\x01\x02%s' "$(cat /etc/network/interfaces)"`
# read link-state using birdc
IFS=$'\n' intf=(`cat /etc/bird/ospf.conf | grep "protocol ospf v3" | cut -d' ' -f4`)
linkstate=''
for i in "${intf[@]}"
do
    linkstate+=`printf '{%s}' "$(birdc show ospf neigh $i)"`
done
str+=`printf '\x01\x02%s' "${linkstate}"`
# submit data
ret=`curl "${url}?t=${token}&m=all&rid=${rid}" -X POST -s --data-binary "$str" -H "Content-Type: application/octet-stream"`
if [[ $ret != *"success"* ]]; then
    # failure, handle exceptions here
fi
