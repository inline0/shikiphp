locals {
  common_tags = {
    project = "demo"
    owner   = "team"
  }
}

module "network" {
  source = "./modules/network"
  cidr   = "10.0.0.0/16"

  dynamic "subnet" {
    for_each = var.subnets
    content {
      name = subnet.value.name
      cidr = subnet.value.cidr
    }
  }
}
