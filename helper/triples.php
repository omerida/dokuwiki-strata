<?php
/**
 * DokuWiki Plugin stratastorage (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Brend Wanders <b.wanders@utwente.nl>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'stratastorage/driver/driver.php');

/**
 * The triples helper is responsible for querying.
 */
class helper_plugin_stratastorage_triples extends DokuWiki_Plugin {
    function helper_plugin_stratastorage_triples() {
        $this->types =& plugin_load('helper', 'stratastorage_types');
    }

    function getMethods() {
        $result = array();
        $result[] = array(
            'name'=> 'initialize',
            'desc'=> 'Sets up a connection to the triple storage.',
            'params'=> array(
                'dsn (optional)'=>'string'
            ),
            'return' => array('success'=>'boolean')
        );
        $result[] = array(
            'name'=>'removeTriples',
            'desc'=>'Removes triples according to a triple pattern.',
            'params'=> array(
                'subject (optional)'=>'string',
                'predicate (optional)'=>'string',
                'object (optional)'=>'string',
                'graph (optional)'=>'string'
            ),
            'return' => array()
        );
        $result[] = array(
            'name'=>'fetchTriples',
            'desc'=>'Fetches all triples matching the given triple pattern.',
            'params'=> array(
                'subject (optional)'=>'string',
                'predicate (optional)'=>'string',
                'object (optional)'=>'string',
                'graph (optional)'=>'string'
            ),
            'return' => array('resultset'=>'array')
        );
        $result[] = array(
            'name'=>'addTriple',
            'desc'=>'Adds a single triple to the store.',
            'params'=> array(
                'subject'=>'string',
                'predicate'=>'string',
                'object'=>'string',
                'graph (optional)'=>'string'
            ),
            'return' => array('success'=>'boolean')
        );
        $result[] = array(
            'name'=>'addTriples',
            'desc'=>'Adds a batch of triples to the store.',
            'params'=> array(
                'triples'=>'array with triples',
                'graph (optional)'=>'string'
            ),
            'return' => array('success'=>'boolean')
        );
        $result[] = array(
            'name'=>'queryRelations',
            'desc'=>'Executes a query, given as an abstract query tree, and returns the resulting rows.',
            'params'=> array(
                'querytree'=>'array'
            ),
            'return' => array('resultset'=>'array')
        );
        $result[] = array(
            'name'=>'queryResources',
            'desc'=>'Executes a query, given as an abstract query tree, and return the resulting resources.',
            'params'=> array(
                'querytree'=>'array'
            ),
            'return' => array('resources'=>'array')
        );

        return $result;
    }

    function getIsaKey() {
        list($type, $hint) = $this->types->getPredicateType();
        return $this->types->loadType($type)->normalize($this->getConf('isa_key'), $hint);
    }

    function getTitleKey() {
        list($type, $hint) = $this->types->getPredicateType();
        return $this->types->loadType($type)->normalize($this->getConf('title_key'), $hint);
    }

    /**
     * Expands tokens in the DSN.
     * 
     * @param str string the string to process
     * @return a string with replaced tokens
     */
    function _expandTokens($str) {
        global $conf;
        $tokens    = array('@METADIR@');
        $replacers = array($conf['metadir']);
        return str_replace($tokens,$replacers,$str);
    }

    /**
     * Initializes the triple helper.
     * 
     * @param dsn string an optional alternative DSN
     * @return true if initialization succeeded, false otherwise
     */
    function initialize($dsn=null) {
        // load default DSN
        if($dsn == null) {
            $dsn = $this->getConf('default_dsn');
            $dsn = $this->_expandTokens($dsn);
        }

        $this->_dsn = $dsn;

        // construct driver
        list($driver,$connection) = explode(':',$dsn,2);
        $driverFile = DOKU_PLUGIN."stratastorage/driver/$driver.php";
        if(!@file_exists($driverFile)) {
            msg('Strata storage: no complementary driver for PDO driver '.$driver.'.',-1);
            return false;
        }
        require_once($driverFile);
        $driverClass = "plugin_strata_driver_$driver";
        $this->_db = new $driverClass($this->getConf('debug'));

        // connect driver
        if(!$this->_db->connect($dsn)) {
            return false;
        }

        // initialize database if necessary
        if(!$this->_db->isInitialized()) {
            $this->_db->initializeDatabase();
        }


        return true;
    }

    /**
     * Makes the an SQL expression case insensitive.
     * 
     * @param a string the expression to process
     * @return a SQL expression
     */
    function _ci($a) {
        return $this->_db->ci($a);
    }

    /**
     * Constructs a case insensitive string comparison in SQL.
     *
     * @param a string the left-hand side
     * @param b string the right-hand side
     *
     * @return a case insensitive SQL string comparison
     */
    function _cic($a, $b) {
        return $this->_ci($a).' = '.$this->_ci($b);
    }

    /**
     * Removes all triples matching the given triple pattern. One or more parameters
     * can be left out to indicate 'any'.
     */
    function removeTriples($subject=null, $predicate=null, $object=null, $graph=null) {
        // construct triple filter
        $filters = array('1 = 1');
        foreach(array('subject','predicate','object','graph') as $param) {
            if($$param != null) {
                $filters[]=$this->_cic($param, '?');
                $values[] = $$param;
            }
        }

        $sql .= "DELETE FROM data WHERE ". implode(" AND ", $filters);

        // prepare query
        $query = $this->_db->prepare($sql);
        if($query == false) return;

        // execute query
        $res = $query->execute($values);
        if($res === false) {
            $error = $query->errorInfo();
            msg(hsc('Strata storage: Failed to remove triples: '.$error[2]),-1);
        }

        $query->closeCursor();
    }

    /**
     * Fetches all triples matching the given triple pattern. Onr or more of
     * parameters can be left out to indicate 'any'.
     */
    function fetchTriples($subject=null, $predicate=null, $object=null, $graph=null) {
        // construct filter
        $filters = array('1 = 1');
        foreach(array('subject','predicate','object','graph') as $param) {
            if($$param != null) {
                $filters[]=$this->_cic($param,'?');
                $values[] = $$param;
            }
        }

        $sql .= "SELECT subject, predicate, object, graph FROM data WHERE ". implode(" AND ", $filters);

        // prepare queyr
        $query = $this->_db->prepare($sql);
        if($query == false) return;

        // execute query
        $res = $query->execute($values);
        if($res === false) {
            $error = $query->errorInfo();
            msg(hsc('Strata storage: Failed to fetch triples: '.$error[2]),-1);
        }

        // fetch results and return them
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        $query->closeCursor();

        return $result;
    }

    /**
     * Adds a single triple.
     * @param subject string
     * @param predicate string
     * @param object string
     * @param graph string
     * @return true of triple was added succesfully, false if not
     */
    function addTriple($subject, $predicate, $object, $graph) {
        return $this->addTriples(array(array('subject'=>$subject, 'predicate'=>$predicate, 'object'=>$object)), $graph);
    }

    /**
     * Adds multiple triples.
     * @param triples array contains all triples as arrays with subject, predicate and object keys
     * @param graph string graph name
     * @return true if the triples were comitted, false otherwise
     */
    function addTriples($triples, $graph) {
        // prepare insertion query
        $sql = "INSERT INTO data(subject, predicate, object, graph) VALUES(?, ?, ?, ?)";
        $query = $this->_db->prepare($sql);
        if($query == false) return false;

        // put the batch in a transaction
        $this->_db->beginTransaction();
        foreach($triples as $t) {
            // insert a single triple
            $values = array($t['subject'],$t['predicate'],$t['object'],$graph);
            $res = $query->execute($values);
            
            // handle errors
            if($res === false) {
                $error = $query->errorInfo();
                msg(hsc('Strata storage: Failed to add triples: '.$error[2]),-1);
                $this->_db->rollBack();
                return false;
            }
            $query->closeCursor();
        }

        // commit and return
        return $this->_db->commit();
    }

    /**
     * Executes the given abstract query tree as a query on the store.
     *
     * @param query array an abstract query tree
     * @return an iterator over the resulting rows
     */
    function queryRelations($queryTree) {
        // create the SQL generator, and generate the SQL query
        $generator = new stratastorage_sql_generator($this);
        list($sql, $literals, $projected) = $generator->translate($queryTree);

        // prepare the query
        $query = $this->_db->prepare($sql);
        if($query === false) {
            return false;
        }

        // execute the query
        $res = $query->execute($literals);
        if($res === false) {
            $error = $query->errorInfo();
            msg(hsc('Strata storage: Failed to execute query: '.$error[2]),-1);
            if($this->getConf('debug')) {
                msg('Debug SQL: <code>'.hsc($sql).'</code>',-1);
                msg('Debug Literals: <pre>'.hsc(print_r($literals,1)).'</pre>',-1);
            }
            return false;
        }

        // wrap the results in an iterator, and return it
        return new stratastorage_relations_iterator($query, $projected);
    }

    /**
     * Executes the abstract query tree, and returns all properties of the matching subjects.
     * This method assumes that the root is a 'select' node.
     * 
     * @param query array the abstract query tree
     * @return an iterator over the resources
     */
    function queryResources($query) {
        // We transform the given query into a resource-centric query as follows:
        //   Remember the single projected variable Vx.
        //   Append two variables ?__p and ?__o to the projection
        //   Add an extra triple pattern (Vx, ?__p, ?__o)
        //   Append Vx to the ordering
        // The query is ready for execution. Result set can be transformed into a
        // resource-centric view by fetching all triples related to a single subject
        // (each subject is in a single continuous block, due to the ordering)

        // add extra tuple
        $query['group'] = array(
            'type'=>'and',
            'lhs'=>$query['group'],
            'rhs'=>array(
                'type'=>'triple',
                'subject'=>array('type'=>'variable','text'=>$query['projection'][0]),
                'predicate'=>array('type'=>'variable','text'=>'__predicate'),
                'object'=>array('type'=>'variable','text'=>'__object')
            )
        );

        // fix projection list
        $query['projection'] = array(
            $query['projection'][0],
            '__predicate',
            '__object'
        );

        // append tuple ordering
        $query['ordering'][] = array(
            'variable'=>$query['projection'][0],
            'direction'=>'asc'
        );

        // execute query
        $result = $this->queryRelations($query);

        if($result === false) {
            return false;
        }

        // invoke iterator that's going to aggregate the resulting relations
        return new stratastorage_resource_iterator($result,$query['projection']);
    }
}

/**
 * SQL generator.
 */
class stratastorage_sql_generator {
    /**
     * Stores all literal values keyed to their placeholder.
     */
    private $literals = array();

    /**
     * Stores all projected variables.
     */
    private $projected = array();

    /**
     * Constructor.
     */
    function stratastorage_sql_generator($triples) {
        $this->_triples = $triples;
        $this->_db = $this->_triples->_db;
    }

    /**
     * Wrap SQL expression in case insensitivisation.
     */
    function _ci($a) {
        return $this->_triples->_ci($a);
    }

    /**
     * Alias generator.
     */
    private $_aliasCounter = 0;
    function _alias($prefix='a') {
        return $prefix.($this->_aliasCounter++);
    }

    /**
     * All used literals.
     */
    private $_literalLookup = array();
    private $_variableLookup = array();

    /**
     * Name generator. Makes the distinction between literals
     * and variables, as they can have the same spelling (and 
     * frequently do in simple queries).
     */
    function _name($term) {
        if($term['type'] == 'variable') {
            // always try the cache
            if(empty($this->_variableLookup[$term['text']])) {
                $this->_variableLookup[$term['text']] = $this->_alias('v');
            }

            return $this->_variableLookup[$term['text']];
        } elseif($term['type'] == 'literal') {
            // always try the cache
            if(empty($this->_literalLookup[$term['text']])) {
                // use aliases to represent literals
                $this->_literalLookup[$term['text']] = $this->_alias('lit');
            }

            // return literal name
            return $this->_literalLookup[$term['text']];
        }
    }

    /**
     * Test whether two things are equal (i.e., equal variables, or equal literals)
     */
    function _patternEquals($pa, $pb) {
        return $pa['type'] == $pb['type'] && $pa['text'] == $pb['text'];
    }

    /**
     * Generates a conditional for the given triple pattern.
     */
    function _genCond($tp) {
        $conditions = array();

        // the subject is a variable
        if($tp['subject']['type'] != 'variable') {
            $id = $this->_alias('qv');
            $conditions[] = $this->_ci('subject').' = '.$this->_ci(':'.$id);
            $this->literals[$id] = $tp['subject']['text'];
        }

        // the predicate is a variable
        if($tp['predicate']['type'] != 'variable') {
            $id = $this->_alias('qv');
            $conditions[] = $this->_ci('predicate').' = '.$this->_ci(':'.$id);
            $this->literals[$id] = $tp['predicate']['text'];
        }

        // the object is a variable
        if($tp['object']['type'] != 'variable') {
            $id = $this->_alias('qv');
            $conditions[] = $this->_ci('object').' = '.$this->_ci(':'.$id);
            $this->literals[$id] = $tp['object']['text'];
        }

        // subject equals predicate
        if($this->_patternEquals($tp['subject'],$tp['predicate'])) {
            $conditions[] = $this->_ci('subject').' = '.$this->_ci('predicate');
        }

        // subject equals object
        if($this->_patternEquals($tp['subject'],$tp['object'])) {
            $conditions[] = $this->_ci('subject').' = '.$this->_ci('object');
        }

        // predicate equals object
        if($this->_patternEquals($tp['predicate'],$tp['object'])) {
            $conditions[] = $this->_ci('predicate').' = '.$this->_ci('object');
        }

        if(count($conditions)!=0) {
            return implode(' AND ',$conditions);
        } else {
            return '1 = 1';
        }
    }

    /**
     * Generates a projection for the given triple pattern.
     */
    function _genPR($tp) {
        $list = array();

        // always project the subject
        $list[] = 'subject AS '.$this->_name($tp['subject']);

        // project the predicate if it's different from the subject
        if(!$this->_patternEquals($tp['subject'], $tp['predicate'])) {
            $list[] = 'predicate AS '.$this->_name($tp['predicate']);
        }

        // project the object if it's different from the subject and different from the predicate
        if(!$this->_patternEquals($tp['subject'], $tp['object']) && !$this->_patternEquals($tp['predicate'],$tp['object'])) {
            $list[] = 'object AS '.$this->_name($tp['object']);
        }

        return implode(', ',$list);
    }

    /**
     * Translates a triple pattern into a graph pattern.
     */
    function _trans_tp($tp) {
        return array(
            'sql'=>'SELECT '.$this->_genPR($tp).' FROM data WHERE '.$this->_genCond($tp),
            'terms'=>array($this->_name($tp['subject']),$this->_name($tp['predicate']), $this->_name($tp['object']))
        );
    }

    /**
     * Translates a group operation on the two graph patterns.
     */
    function _trans_group($gp1, $gp2, $join) {
        // determine the resulting terms
        $terms = array_unique(array_merge($gp1['terms'], $gp2['terms']));

        // determine the overlapping terms (we need to coalesce these)
        $common = array_intersect($gp1['terms'], $gp2['terms']);

        // determine the non-overlapping terms (we can project them directly)
        $fields = array_diff($terms, $common);

        // handle overlapping terms by coalescing them into a single term
        if(count($common)>0) {
            $intersect = array();
            foreach($common as $c) {
                $intersect[] = '('.$this->_ci('r1.'.$c).' = '.$this->_ci('r2.'.$c).' OR r1.'.$c.' IS NULL OR r2.'.$c.' IS NULL)';
                $fields[]='COALESCE(r1.'.$c.', r2.'.$c.') AS '.$c;
            }
            $intersect = implode(' AND ',$intersect);
        } else {
            $intersect = '1';
        }

        $fields = implode(', ',$fields);

        return array(
            'sql'=>'SELECT DISTINCT '.$fields.' FROM ('.$gp1['sql'].') AS r1 '.$join.' ('.$gp2['sql'].') AS r2 ON '.$intersect,
            'terms'=>$terms
        );
    }

    /**
     * Translate an optional operation.
     */
    function _trans_opt($query) {
        $gp1 = $this->_dispatch($query['lhs']);
        $gp2 = $this->_dispatch($query['rhs']);
        return $this->_trans_group($gp1, $gp2, 'LEFT OUTER JOIN');
    }

    /**
     * Translate an and operation.
     */
    function _trans_and($query) {
        $gp1 = $this->_dispatch($query['lhs']);
        $gp2 = $this->_dispatch($query['rhs']);
        return $this->_trans_group($gp1, $gp2, 'INNER JOIN');
    }

    /**
     * Translate a filter operation. The filters are a conjunction of separate expressions.
     */
    function _trans_filter($query) {
        $gp = $this->_dispatch($query['lhs']);
        $fs = $query['rhs'];

        $filters = array();

        foreach($fs as $f) {
            // determine representation of left-hand side
            if($f['lhs']['type'] == 'variable') {
                $lhs = $this->_name($f['lhs']);
            } else {
                $id = $this->_alias('qv');
                $lhs = ':'.$id;
                $this->literals[$id] = $f['lhs']['text'];
            }

            // determine representation of right-hand side
            if($f['rhs']['type'] == 'variable') {
                $rhs = $this->_name($f['rhs']);
            } else {
                $id = $this->_alias('qv');
                $rhs = ':'.$id;
                $this->literals[$id] = $f['rhs']['text'];
            }

            // the escaping constants (head, tail and modifier)
            $eh= "REPLACE(REPLACE(REPLACE(";
            $et= ",'!','!!'),'_','!_'),'%','!%')";
            $em= " ESCAPE '!'";

            // handle different operators
            switch($f['operator']) {
                case '=':
                case '!=':
                    $filters[] = '( ' . $this->_ci($lhs) . ' '.$f['operator'].' ' . $this->_ci($rhs). ' )';
                    break;
                case '>':
                case '<':
                case '>=':
                case '<=':
                    $filters[] = '( ' . $this->_triples->_db->castToNumber($lhs) . ' ' . $f['operator'] . ' ' . $this->_triples->_db->castToNumber($rhs) . ' )';
                    break;
                case '~':
                    $filters[] = '( ' . $this->_ci($lhs) . ' '.$this->_db->stringCompare().' '. $this->_ci('(\'%\' || ' .$eh.$rhs.$et. ' || \'%\')') .$em. ')';
                    break;
                case '!~':
                    $filters[] = '( ' . $this->_ci($lhs) . ' NOT '.$this->_db->stringCompare().' '. $this->_ci('(\'%\' || ' . $eh.$rhs.$et. ' || \'%\')') .$em. ')';
                    break;
                case '^~':
                    $filters[] = '( ' . $this->_ci($lhs) . ' '.$this->_db->stringCompare().' ' .$this->_ci('('. $eh.$rhs.$et . ' || \'%\')').$em. ')';
                    break;
                case '!^~':
                    $filters[] = '( ' . $this->_ci($lhs) . ' NOT '.$this->_db->stringCompare().' ' .$this->_ci('('. $eh.$rhs.$et . ' || \'%\')').$em. ')';
                    break;
                case '$~':
                    $filters[] = '( ' . $this->_ci($lhs) . ' '.$this->_db->stringCompare().' '.$this->_ci('(\'%\' || ' . $eh.$rhs.$et. ')') .$em. ')';
                    break;
                case '!$~':
                    $filters[] = '( ' . $this->_ci($lhs) . ' NOT '.$this->_db->stringCompare().' '.$this->_ci('(\'%\' || ' . $eh.$rhs.$et. ')') .$em. ')';
                    break;

                default:
            }
        }

        $filters = implode(' AND ', $filters);

        return array(
            'sql'=>'SELECT * FROM ('.$gp['sql'].') r WHERE '.$filters,
            'terms'=>$gp['terms']
        );
    }

    /**
     * Translate minus operation.
     */
    function _trans_minus($query) {
        $gp1 = $this->_dispatch($query['lhs']);
        $gp2 = $this->_dispatch($query['rhs']);

        // determine overlapping terms (we need to substitute these)
        $common = array_intersect($gp1['terms'], $gp2['terms']);

        // create conditional that 'substitutes' terms by requiring equality
        $terms = array();
        foreach($common as $c) {
            $terms[] = '('.$this->_ci('r1.'.$c).' = '.$this->_ci('r2.'.$c).')';
        }

        if(count($terms)>0) {
            $terms = implode(' AND ',$terms);
        } else {
            $terms = '1=1';
        }

        return array(
            'sql'=>'SELECT DISTINCT * FROM ('.$gp1['sql'].') r1 WHERE NOT EXISTS (SELECT * FROM ('.$gp2['sql'].') r2 WHERE '.$terms.')',
            'terms'=>$gp1['terms']
        );
    }

    /**
     * Translate union operation.
     */
    function _trans_union($query) {
        // dispatch the child graph patterns
        $gp1 = $this->_dispatch($query['lhs']);
        $gp2 = $this->_dispatch($query['rhs']);

        // dispatch them again to get new literal binding aliases
        // (This is required by PDO, as no named variable may be used twice)
        $gp1x = $this->_dispatch($query['lhs']);
        $gp2x = $this->_dispatch($query['rhs']);

        // determine non-overlapping terms
        $ta = array_diff($gp1['terms'], $gp2['terms']);
        $tb = array_diff($gp2['terms'], $gp1['terms']);

        // determine overlapping terms
        $tc = array_intersect($gp1['terms'], $gp2['terms']);

        // determine final terms
        $terms = array_unique(array_merge($gp1['terms'], $gp2['terms']));

        // construct selected term list
        $sa = array_merge($ta, $tb);
        $sb = array_merge($ta, $tb);

        // append common terms with renaming
        foreach($tc as $c) {
            $sa[] = 'r1.'.$c.' AS '.$c;
            $sb[] = 'r3.'.$c.' AS '.$c;
        }

        $sa = implode(', ', $sa);
        $sb = implode(', ', $sb);

        return  array(
            'sql'=>'SELECT DISTINCT '.$sa.' FROM ('.$gp1['sql'].') r1 LEFT OUTER JOIN ('.$gp2['sql'].') r2 ON (1=0) UNION SELECT DISTINCT '.$sb.' FROM ('.$gp2x['sql'].') r3 LEFT OUTER JOIN ('.$gp1x['sql'].') r4 ON (1=0)',
            'terms'=>$terms
        );
    }

    /**
     * Translate projection and ordering.
     */
    function _trans_select($query) {
        $gp = $this->_dispatch($query['group']);
        $vars = $query['projection'];
        $order = $query['ordering'];
        $terms = array();
        $fields = array();

        // determine exact projection
        foreach($vars as $v) {
            // determine projection translation
            $name = $this->_name(array('type'=>'variable','text'=>$v));

            // fix projected variable into SQL
            $terms[] = $name;
            $fields[] = $name;

            // store projection translation
            $this->projected[$name] = $v;
        }

        // assign ordering if required
        $ordering = array();
        foreach($order as $o) {
            $name = $this->_name(array('type'=>'variable','text'=>$o['variable']));
            $orderTerms = $this->_db->orderBy($name);
            foreach($orderTerms as $term) {
                $a = $this->_alias('o');
                $fields[] = "$term AS $a";
                $ordering[] = "$a ".($o['direction'] == 'asc'?'ASC':'DESC');
            }
        }

        // construct select list
        $fields = implode(', ',$fields);

        // construct ordering
        if(count($ordering)>0) {
            $ordering = ' ORDER BY '.implode(', ',$ordering);
        } else {
            $ordering = '';
        }

        return array(
            'sql'=>'SELECT DISTINCT '.$fields.' FROM ('.$gp['sql'].') r'.$ordering,
            'terms'=>$terms
        );
    }

    function _dispatch($query) {
        switch($query['type']) {
            case 'select':
                return $this->_trans_select($query);
            case 'union':
                return $this->_trans_union($query);
            case 'minus':
                return $this->_trans_minus($query);
            case 'optional':
                return $this->_trans_opt($query);
            case 'filter':
                return $this->_trans_filter($query);
            case 'triple':
                return $this->_trans_tp($query);
            case 'and':
                return $this->_trans_and($query);
            default:
                msg('Strata storage: unkown abstract query tree node \''.hsc($query['type']).'\'.',-1);
                return array('sql'=>'<<INVALID QUERY NODE>>', array());
        }
    }

    /**
     * Translates an abstract query tree to SQL.
     */
    function translate($query) {
        $q = $this->_dispatch($query);
        return array($q['sql'], $this->literals, $this->projected);
    }
}

/**
 * This iterator is used to offer an interface over a
 * relations query result.
 */
class stratastorage_relations_iterator implements Iterator {
    function __construct($pdostatement, $projection) {
        // backend iterator
        $this->data = $pdostatement;

        // state information
        $this->closed = false;
        $this->id = 0;

        // projection data
        $this->projection = $projection;

        // initialize the iterator
        $this->next();
    }
    
    function current() {
        return $this->row;
    }

    function key() {
        return $this->id;
    }

    function next() {
        // fetch...
        $this->row = $this->data->fetch(PDO::FETCH_ASSOC);

        if($this->row) {
            $row = array();

            // ...project...
            foreach($this->projection as $alias=>$field) {
                $row[$field] = $this->row[$alias];
            }
            $this->row = $row;

            // ...and increment the id.
            $this->id++;
        }

        // Close the backend if we're out of rows.
        // (This should not be necessary if everyone closes
        // their iterator after use -- but experience dictates that
        // this is a good safety net)
        if(!$this->valid()) {
            $this->closeCursor();
        }
    }

    function rewind() {
        // noop
    }

    function valid() {
        return $this->row != null;
    }

    /**
     * Closes this result set.
     */
    function closeCursor() {
        if(!$this->closed) {
            $this->data->closeCursor();
            $this->closed = true;
        }
    }
}

/**
 * This iterator is used to offer an interface over a
 * resources query result.
 */
class stratastorage_resource_iterator implements Iterator {
    function __construct($relations, $projection) {
        // backend iterator (ordered by tuple)
        $this->data = $relations;

        // state information
        $this->closed = false;
        $this->valid = true;
        $this->item = null;
        $this->subject = null;

        // projection data
        list($this->__subject, $this->__predicate, $this->__object) = $projection;

        // initialize the iterator
        $this->next();
    }
    
    function current() {
        return $this->item;
    }

    function key() {
        return $this->subject;
    }

    function next() {
        if(!$this->data->valid()) {
            $this->valid = false;
            return;
        }

        // the current relation
        $peekRow = $this->data->current();

        // construct a new subject
        $this->item = array();
        $this->subject = $peekRow[$this->__subject];

        // continue aggregating data as long as the subject doesn't change and
        // there is data available
        while($this->data->valid() && $peekRow[$this->__subject] == $this->subject) {
            $p = $peekRow[$this->__predicate];
            $o = $peekRow[$this->__object];
            if(!isset($this->item[$p])) $this->item[$p] = array();
            $this->item[$p][] = $o;
            
            $this->data->next();
            $peekRow = $this->data->current();
        }

        return $this->item;
    }

    function rewind() {
        // noop
    }

    function valid() {
        return $this->valid;
    }

    /**
     * Closes this result set.
     */
    function closeCursor() {
        if(!$this->closed) {
            $this->data->closeCursor();
            $this->closed = true;
        }
    }
}
