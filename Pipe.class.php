<?
/* Pipe.class.php - Class for opening handles to applications
 * Copyright (C) 2007 Erik Osterman <e@osterman.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/* File Authors:
 *   Erik Osterman <e@osterman.com>
 */



class Pipe implements Iterator
{
	const STDIN  = 0;
	const STDOUT = 1;
	const STDERR = 2;
	
	protected $descriptors;
	protected $process;
	protected $env;
	protected $cwd;
	protected $pipes;
  protected $iterator;
  protected $history;
  
	public function __construct( $cmd )
	{
		$this->descriptors = Array(
									Pipe::STDIN  => Array("pipe", "r"),  
									Pipe::STDOUT => Array("pipe", "w"), 
									Pipe::STDERR => Array("pipe", "w")
								);
		$this->cmd      = $cmd;
		$this->pipes    = Array();
		$this->env      = Array();
		$this->process  = null;
    $this->iterator = null;
	}

  public function __destruct()
  {
		if( $this->attached ) 
			$this->close();
    unset($this->descriptors);
    unset($this->process);
    unset($this->env);
    unset($this->cwd);
    unset($this->pipes);
    unset($this->iterator);
    unset($this->history);
  }

	public function execute()
	{
    $args = func_get_args();
    if(count($args) == 1)
      if(is_array($args[0]))
        $args = $args[0];
    foreach( $args as &$arg )
      $arg = escapeshellarg( $arg );
    $cmd = $this->cmd . " " . join(" " , $args ) ;
    // print "EXECUTE: " . $cmd . "\n";
    $this->history = $cmd;
		$this->process = proc_open( $cmd, $this->descriptors, $this->pipes, $this->cwd, $this->env);
		if( !$this->attached ) 
			throw new Exception( get_class($this) . "::exec failed!");
	}

	public function __get( $property )
	{
		switch( $property )
		{
			case 'stdin':
				if( array_key_exists( Pipe::STDIN, $this->pipes) )
					return $this->pipes[ Pipe::STDIN ];
				else throw new Exception( get_class($this) . "::$property not opened");
			case 'stdout':
				if( array_key_exists( Pipe::STDOUT, $this->pipes) )
					return $this->pipes[ Pipe::STDOUT ];
				else throw new Exception( get_class($this) . "::$property not opened");
			case 'stderr':
				if( array_key_exists( Pipe::STDERR, $this->pipes) )
					return $this->pipes[ Pipe::STDERR ];
				else throw new Exception( get_class($this) . "::$property not opened");
			case 'execute':
				return $this->execute();
			case 'close':
				return $this->close();
			case 'attached':
				return $this->attached();

      case 'history':
        return $this->history;
		
			case 'command':
			case 'pid':
			case 'running':
			case 'signaled':
			case 'stopped':
			case 'existcode':
			case 'termsig':
			case 'stopsig':
				return $this->status($property);
				
			case 'cmd':
				return $this->cmd;
			default:
				throw new Exception( get_class($this) . "::$property not handled");
		}
	}

	public function __set( $property, $value )
	{
		switch( $property )
		{
			case 'env':
				if( Type::arr($value) )
					return $this->env = $value;
				else
					throw new Exception( get_class($this) . "::$property should be an array of k/v pairs");
			case 'cmd':
        list($cmd) = split(' ', $value);
				if( file_exists($cmd) && posix_access($cmd, POSIX_R_OK | POSIX_X_OK)   )
					return $this->cmd = $value;
				else
					throw new Exception( get_class($this) . "::$property $value does not exist or cannot be executed");
			default:
				throw new Exception( get_class($this) . "::$property cannot be set");
		}
	}
	
	public function __unset($property)
	{
		throw new Exception( get_class($this) . "::$property cannot be unset");
	}

	public function close()
	{
		if( $this->attached )
		{
		   proc_close($this->process);
		   $this->process = null;
		} else
			throw new Exception( get_class($this) . "::close not attached to any process");
	}

	public function terminate()
	{
		if( $this->attached )
		{
			$args = func_get_args();
			array_unshift($args, $this->process);
			call_user_func_array( 'proc_terminate' , $args);
			$this->process = null;
		} else
			throw new Exception( get_class($this) . "::terminate not attached to any process");
	}

	public function status( $element = null )
	{
		if( $this->attached )
		{
			$result = proc_get_status( $this->process );
			if( is_null($element) )
				return $result;
			elseif( array_key_exists($element, $result) )
				return $result[$element];
			else
				throw new Exception( get_class($this) . "::status $element not defined");
		} else
			throw new Exception( get_class($this) . "::status not attached to any process");
	}

	public function attached()
	{
		return is_resource($this->process);
	}

  // Iterator Methods
  public function rewind()
  {
    return $this->next();
  }

  public function current()
  {
    return $this->iterator;
  }

  public function key()
  {
    throw new Exception( get_class($this) . "::key not implemented");
  }

  public function next()
  {
    $this->iterator = fgets( $this->stdout );
    return $this->iterator;
  }

  public function prev()
  {
    throw new Exception( get_class($this) . "::prev not implemented");
  }

  public function valid()
  {
    return !feof( $this->stdout );
  }

}

/*
// Example Usage:
$pipe = new Pipe( '/bin/cat' );
$pipe->execute;
print $pipe->pid . "\n";
fwrite($pipe->stdin, "HEY\n");
print fread($pipe->stdout, 1024);
//$pipe->terminate();
*/

?>
