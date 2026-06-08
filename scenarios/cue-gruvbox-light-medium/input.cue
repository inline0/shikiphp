package config

#Server: {
	host: string
	port: int & >0 & <65536 | *8080
	tls:  bool | *false
}

servers: [Name=string]: #Server & {
	host: Name
}

servers: {
	web: {port: 80, tls: true}
	api: {port: 9000}
}

for name, s in servers {
	endpoints: "\(name)": "\(s.host):\(s.port)"
}
