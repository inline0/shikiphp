#!/bin/bash
NAME="world"

cat <<EOF > /tmp/config.txt
hello $NAME
path is $HOME
literal: \$NOT_EXPANDED
EOF

read -r -d '' MSG <<-MSG
	greeting for $NAME
	on host $HOSTNAME
MSG
echo "$MSG"
