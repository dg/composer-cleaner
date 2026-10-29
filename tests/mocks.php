<?php declare(strict_types=1);

trait CallLog
{
	private array $log = [];


	public function getLog(): array
	{
		$res = $this->log;
		$this->log = [];
		return $res;
	}
}


class IOMock extends Composer\IO\NullIO
{
	use CallLog;

	public function write($messages, bool $newline = true, int $verbosity = self::NORMAL): void
	{
		$this->log[] = [__FUNCTION__, func_get_args()];
	}


	public function writeError($messages, bool $newline = true, int $verbosity = self::NORMAL): void
	{
		$this->log[] = [__FUNCTION__, func_get_args()];
	}
}


class FilesystemMock extends Composer\Util\Filesystem
{
	use CallLog;

	public function remove(string $file): bool
	{
		$this->log[] = [__FUNCTION__, func_get_args()];
		return true;
	}
}
