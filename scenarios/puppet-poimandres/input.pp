class nginx (
  String $package_name = 'nginx',
  Integer $port = 80,
) {
  package { $package_name:
    ensure => installed,
  }

  service { 'nginx':
    ensure  => running,
    enable  => true,
    require => Package[$package_name],
  }

  file { '/etc/nginx/nginx.conf':
    content => template('nginx/nginx.conf.erb'),
    notify  => Service['nginx'],
  }
}
