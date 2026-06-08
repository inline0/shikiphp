param location string = resourceGroup().location
param storageName string

@allowed([
  'Standard_LRS'
  'Standard_GRS'
])
param sku string = 'Standard_LRS'

resource storage 'Microsoft.Storage/storageAccounts@2023-01-01' = {
  name: storageName
  location: location
  sku: {
    name: sku
  }
  kind: 'StorageV2'
}

output storageId string = storage.id
