import "@typespec/http";

using TypeSpec.Http;

@service({ title: "Pet Store" })
namespace PetStore;

model Pet {
  id: int32;
  name: string;
  tags?: string[];
}

@route("/pets")
interface Pets {
  @get list(): Pet[];
  @post create(@body pet: Pet): Pet;
}
