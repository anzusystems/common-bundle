Tests
============

---

Because Anzu Common Bundle requires [AnzuKernel](https://github.com/anzusystems/common-bundle/blob/main/src/Kernel/AnzuKernel.php), your tests should not rely on Symfony default `KernelTestCase` or `WebTestCase`. 

They always try to create kernel instance by injecting only two properties: `$environment`, `$debug`. 

AnzuKernel requires more mandatory properties, therefore use one of: 
* [AnzuKernelTestCase](https://github.com/anzusystems/common-bundle/blob/main/src/Tests/AnzuKernelTestCase.php) 
* [AnzuWebTestCase](https://github.com/anzusystems/common-bundle/blob/main/src/Tests/AnzuWebTestCase.php).

Both Anzu test cases overrides default kernel factory method provided by Symfony.

MCP tools
---

A tool test extends `AnzuSystems\CommonBundle\Tests\Mcp\AbstractMcpTestCase` and talks to the endpoint over HTTP, so it sees
what a client sees: the token, the input schema of the SDK, the `tools/list` filter and the serialized response. The host
implements `createMcpToken()`, `getMcpHost()` and `getMcpPath()`.

```php
final class ListDesksToolTest extends AbstractMcpTestCase
{
    public function testForeignSiteGroupIsDenied(): void
    {
        $result = $this->getMcpClient(User::ID_LIMITED)
            ->callTool(ListDesksTool::class, ['siteGroupId' => self::FOREIGN_ID]);

        self::assertToolAccessDenied($result);
    }
}
```

* `getMcpClient($userId, $writeTools, $readTools)` gives a client with a token of that user and scope; it opens its session
  on the first call. `callTool()` returns `McpToolResult` with `isError`, `error` and `data`.
* `assertToolSucceeded()`, `assertToolError()`, `assertToolAccessDenied()`, `assertToolInvalidArgument()`,
  `assertToolUnknownId()` and `assertToolUnknownIds()` compare the whole message, so another refusal cannot pass for this one.
* A value the input schema refuses never reaches the validator of the request: the test expects the error of the SDK.
* The entity manager is cleared between requests. Load an entity after the call, and load it again to change it after a preview.
* Services with a reset, such as an array cache pool, are emptied on the next request. A limit or a reservation that must
  survive a request needs a pool without `kernel.reset` or a `kernel->boot()` before it is written.
