<?php
namespace wcf\system;
use phpline\console\ConsoleReader;
use phpline\internal\Log;
use phpline\TerminalFactory;
use wcf\system\cli\command\CommandHandler;
use wcf\system\cli\command\CommandNameCompleter;
use wcf\system\cli\DatabaseCommandHistory;
use wcf\system\event\EventHandler;
use wcf\system\exception\IllegalLinkException;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\exception\SystemException;
use wcf\system\exception\UserInputException;
use wcf\system\language\LanguageFactory;
use wcf\system\package\PackageUpdateDispatcher;
use wcf\system\user\authentication\UserAuthenticationFactory;
use wcf\util\CLIUtil;
use wcf\util\StringUtil;
use Zend\Console\Adapter\Posix;
use Zend\Console\Exception\RuntimeException as ArgvException;
use Zend\Console\ColorInterface as Color;
use Zend\Console\Getopt as ArgvParser;
use Zend\Loader\StandardAutoloader as ZendLoader;

/**
 * Extends WCF class with functions for CLI.
 *
 * @author	Tim Düsterhus
 * @copyright	2001-2012 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf
 * @subpackage	system
 * @category	Community Framework
 */
class CLIWCF extends WCF {
	/**
	 * instance of ConsoleReader
	 * @var phpline\console\ConsoleReader
	 */
	protected static $consoleReader = null;
	
	/**
	 * instance of ArgvParser
	 * @var Zend\Console\Getopt
	 */
	protected static $argvParser = null;
	
	/**
	 * Calls all init functions of the WCF class.
	 */
	public function __construct() {
		parent::__construct();
		
		// the destructor registered in core.functions.php will only call the destructor of the parent class
		register_shutdown_function(array('wcf\system\CLIWCF', 'destruct'));
		
		// register additional autoloaders
		require_once(WCF_DIR.'lib/system/api/phpline/phpline.phar');
		require_once(WCF_DIR.'lib/system/api/zend/Loader/StandardAutoloader.php');
		$zendLoader = new ZendLoader(array(ZendLoader::AUTOREGISTER_ZF => true));
		$zendLoader->register();
		
		$this->initArgv();
		$this->initPHPLine();
		$this->initAuth();
		// TODO: Show whether there are updates available (similar to TTYs at Ubuntu Linux)
		$this->checkForUpdates();
		$this->initCommands();
	}
	
	/**
	 * Destroys the session.
	 * 
	 * @see wcf\system\WCF::destruct()
	 */
	public static function destruct() {
		if (self::getReader() !== null && self::getReader()->getHistory() instanceof DatabaseCommandHistory) {
			self::getReader()->getHistory()->save();
			self::getReader()->getHistory()->autoSave = false;
		}
		
		self::getSession()->delete();
	}
	
	/**
	 * Initializes parsing of command line options.
	 */
	protected function initArgv() {
		// initialise ArgvParser
		self::$argvParser = new ArgvParser(array(
			'language=s' => WCF::getLanguage()->get('wcf.cli.help.language'),
			'v' => WCF::getLanguage()->get('wcf.cli.help.v'),
			'q' => WCF::getLanguage()->get('wcf.cli.help.q'),
			'h|help-s' => WCF::getLanguage()->get('wcf.cli.help.help'),
			'version' => WCF::getLanguage()->get('wcf.cli.help.version'),
			'disableColors' => WCF::getLanguage()->get('wcf.cli.help.disableColors'),
			'disableUpdateCheck' => WCF::getLanguage()->get('wcf.cli.help.disableUpdateCheck'),
			'exitOnFail' => WCF::getLanguage()->get('wcf.cli.help.exitOnFail')
		));
		self::getArgvParser()->setOptions(array(
			ArgvParser::CONFIG_CUMULATIVE_FLAGS => true,
			ArgvParser::CONFIG_DASHDASH => false
		));
		
		// parse arguments
		EventHandler::getInstance()->fireAction($this, 'beforeArgumentParsing');
		try {
			self::getArgvParser()->parse();
		}
		catch (ArgvException $e) {
			// show error message and usage
			echo $e->getMessage().PHP_EOL;
			echo self::getArgvParser()->getUsageMessage();
			exit;
		}
		EventHandler::getInstance()->fireAction($this, 'afterArgumentParsing');
		
		// handle arguments
		if (self::getArgvParser()->help === true) {
			// show usage
			echo self::getArgvParser()->getUsageMessage();
			exit;
		}
		else if (self::getArgvParser()->help) {
			$help = WCF::getLanguage()->get('wcf.cli.help.'.self::getArgvParser()->help.'.description', true);
			if ($help) echo $help.PHP_EOL;
			else {
				echo WCF::getLanguage()->getDynamicVariable('wcf.cli.noLongHelp', array('topic' => self::getArgvParser()->help)).PHP_EOL;
			}
			exit;
		}
		if (self::getArgvParser()->version) {
			// show version
			echo WCF_VERSION.PHP_EOL;
			exit;
		}
		if (self::getArgvParser()->language) {
			// set language
			$language = LanguageFactory::getInstance()->getLanguageByCode(self::getArgvParser()->language);
			if ($language === null) {
				echo WCF::getLanguage()->getDynamicVariable('wcf.cli.error.language.notFound', array('languageCode' => self::getArgvParser()->language)).PHP_EOL;
				exit;
			}
			self::setLanguage($language->languageID);
		}
		if (in_array('moo', self::getArgvParser()->getRemainingArgs())) {
			echo '...."Have you mooed today?"...'.PHP_EOL;
		}
		
		define('VERBOSITY', self::getArgvParser()->v - self::getArgvParser()->q);
		if (VERBOSITY >= 2) Log::enableDebug();
		if (VERBOSITY >= 3) Log::enableTrace();
	}
	
	/**
	 * Returns the argv parser.
	 * 
	 * @return Zend\Console\Getopt
	 */
	public static function getArgvParser() {
		return self::$argvParser;
	}
	
	/**
	 * Initializes PHPLine.
	 */
	protected function initPHPLine() {
		$terminal = TerminalFactory::get();
		self::$consoleReader = new ConsoleReader("WoltLab Community Framework", null, null, $terminal);
		
		// don't expand events, as the username and password will follow
		self::getReader()->setExpandEvents(false);
		
		if (VERBOSITY >= 0) {
			$headline = str_pad("WoltLab (r) Community Framework (tm) ".WCF_VERSION, self::getTerminal()->getWidth(), " ", STR_PAD_BOTH);
			self::getReader()->println($headline);
		}
	}
	
	/**
	 * Returns ConsoleReader.
	 * 
	 * @return phpline\console\ConsoleReader
	 */
	public static function getReader() {
		return self::$consoleReader;
	}
	
	/**
	 * Returns the terminal that is attached to ConsoleReader
	 * 
	 * @return phpline\Terminal
	 */
	public static function getTerminal() {
		return self::getReader()->getTerminal();
	}
	
	/**
	 * Does the user authentification.
	 */
	protected function initAuth() {
		do {
			$line = self::getReader()->readLine(WCF::getLanguage()->get('wcf.user.username').'> ');
			if ($line === null) exit;
			$username = StringUtil::trim($line);
		}
		while ($username === '');
		
		do {
			$line = self::getReader()->readLine(WCF::getLanguage()->get('wcf.user.password').'> ', '*');
			if ($line === null) exit;
			$password = StringUtil::trim($line);
		}
		while ($password === '');
		
		try {
			$user = UserAuthenticationFactory::getInstance()->getUserAuthentication()->loginManually($username, $password);
			WCF::getSession()->changeUser($user);
		}
		catch (UserInputException $e) {
			$message = WCF::getLanguage()->getDynamicVariable('wcf.user.'.$e->getField().'.error.'.$e->getType(), array('username' => $username));
			self::getReader()->println($message);
			exit(1);
		}
		
		// initialize history
		$history = new DatabaseCommandHistory();
		$history->load();
		self::getReader()->setHistory($history);
		
		// initialize language
		if (!self::getArgvParser()->language) $this->initLanguage();
	}
	
	/**
	 * Initializes command handling.
	 */
	protected function initCommands() {
		// add command name completer
		self::getReader()->addCompleter(new CommandNameCompleter());
		
		while (true) {
			// roll back open transactions of the previous command, as they are dangerous in a long living script
			if (WCF::getDB()->rollBackTransaction()) {
				Log::warn('Previous command had an open transaction.');
			}
			$line = self::getReader()->readLine('>');
			if ($line === null) exit;
			$line = StringUtil::trim($line);
			try {
				$command = CommandHandler::getCommand($line);
				$command->execute(CommandHandler::getParameters($line));
			}
			catch (IllegalLinkException $e) {
				self::getReader()->println(WCF::getLanguage()->getDynamicVariable('wcf.cli.error.command.notFound', array('command' => $line)));
				
				if (self::getArgvParser()->exitOnFail) {
					exit(1);
				}
				continue;
			}
			catch (PermissionDeniedException $e) {
				self::getReader()->println(WCF::getLanguage()->getDynamicVariable('wcf.global.error.permissionDenied'));
				
				if (self::getArgvParser()->exitOnFail) {
					exit(1);
				}
				continue;
			}
			catch (ArgvException $e) {
				// show error message and usage
				if ($e->getMessage()) echo $e->getMessage().PHP_EOL;
				echo str_replace($_SERVER['argv'][0], CommandHandler::getCommandName($line), $e->getUsageMessage());
				
				if (self::getArgvParser()->exitOnFail) {
					exit(1);
				}
				continue;
			}
			catch (\Exception $e) {
				Log::error($e);
				
				if (self::getArgvParser()->exitOnFail) {
					exit(1);
				}
				continue;
			}
		}
	}
	
	/**
	 * Checks for updates.
	 * 
	 * @return	string
	 */
	public function checkForUpdates() {
		if (VERBOSITY >= -1 && !self::getArgvParser()->disableUpdateCheck) {
			$updates = PackageUpdateDispatcher::getInstance()->getAvailableUpdates();
			if (!empty($updates)) {
				$return = self::getReader()->println(count($updates) . ' updates are available');
				
				if (VERBOSITY >= 1) {
					$table = array(
						array(
							WCF::getLanguage()->get('wcf.acp.package.name'),
							WCF::getLanguage()->get('wcf.acp.package.version'),
							WCF::getLanguage()->get('wcf.acp.package.newVersion')
						)
					);
					
					$posix = new Posix();
					foreach ($updates as $update) {
						$row = array(
							WCF::getLanguage()->get($update['packageName']),
							$update['packageVersion'],
							$update['version']['packageVersion']
						);
						
						// TODO: Check whether update is important
						if ($update['version']['isCritical'] && self::getTerminal()->isAnsiSupported() && !self::getArgvParser()->disableColors) {
							$row[2] = $posix->colorize($row[2], Color::RED);
						}
						
						$table[] = $row;
					}
					
					self::getReader()->println(CLIUtil::generateTable($table));
				}
			}
		}
	}
}
