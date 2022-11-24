Tests
============

---

Because Anzu Common Bundle requires [AnzuKernel](https://github.com/anzusystems/common-bundle/blob/main/src/Kernel/AnzuKernel.php), your tests should not rely on Symfony default `KernelTestCase` or `WebTestCase`. 

They always try to create kernel instance by injecting only two properties: `$environment`, `$debug`. 

AnzuKernel requires more mandatory properties, therefore use one of: 
* [AnzuKernelTestCase](https://github.com/anzusystems/common-bundle/blob/main/src/Tests/AnzuKernelTestCase.php) 
* [AnzuWebTestCase](https://github.com/anzusystems/common-bundle/blob/main/src/Tests/AnzuWebTestCase.php).

Both Anzu test cases overrides default kernel factory method provided by Symfony.
