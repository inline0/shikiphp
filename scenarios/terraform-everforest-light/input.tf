variable "region" {
  type    = string
  default = "us-east-1"
}

resource "aws_instance" "web" {
  count         = 2
  ami           = data.aws_ami.ubuntu.id
  instance_type = "t3.micro"

  tags = {
    Name = "web-${count.index}"
    Env  = var.region
  }
}

output "ips" {
  value = [for i in aws_instance.web : i.private_ip]
}
