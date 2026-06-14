# libMesh GmshIO $Nodes uncontrolled allocation PoC

## Build and run

```sh
docker build -t poc-libmesh-gmsh-nodes-oom .
docker run --rm poc-libmesh-gmsh-nodes-oom
```

## Expected vulnerable behavior

The vulnerable commit should abort under AddressSanitizer or fail with an excessive allocation / out-of-memory condition when parsing the crafted `.msh` file. The file declares an extremely large `$Nodes` count, and `GmshIO::read_mesh()` reserves memory for that count before validating that the file contains that many nodes or applying a sane maximum.

## Affected commit

`libMesh/libmesh@cc84387bbd96e12d2d12986402ec8bba282fc0c4`

## Trigger input

```text
$MeshFormat
2.2 0 8
$EndMeshFormat
$Nodes
4294967295
```

## Expected correct behavior

The parser should reject the malformed `.msh` file before allocating memory proportional to the untrusted node count.
