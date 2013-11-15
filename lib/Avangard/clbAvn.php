<?php

namespace Avangard;

use \HttpMessage;
use \HttpRequest;
use \HttpQueryString;
use \DateTime;
use \Exception;

class clbAvn
{
    private $options = array(
        'useragent' => 'API Emulator',
        'connecttimeout' => 60,
        'timeout' => 60,
        'redirect' => 0,// handle redirects manually
    );
    private $cookies = null;

    public function __construct($login, $password, array $cookies = null) {
        if (is_array($cookies)) {
            $this->cookies = $cookies;
        } else {
            $this->authenticate($login, $password);
        }
    }

    private function _dumpHttpMessage(HttpMessage $msg) {
        $result = array();
        foreach ($msg->getHeaders() as $key => $value) {
            $result[] = "${key}: $value";
        }
        $result[] = '';
        $result[] = $msg->getBody();
        return implode("\n", $result);
    }

    private function authenticate($login, $password) {
        $url = 'https://www.avangard.ru/client4/afterlogin';
        $request = new HttpRequest($url, HttpRequest::METH_POST, $this->options);
        $request->setContentType('application/x-www-form-urlencoded');
        $request->setPostFields(array(
            'login' => $login,
            'passwd' => $password,
            'nw' => '1',
        ));
        $msg = $request->send();
        if ($request->getResponseCode() != 301) {
            throw new Exception(sprintf("POST to '%s' expects 301 status, got %d\n%s",
                    $msg->getRequestUrl(), $request->getResponseCode(), $this->_dumpHttpMessage($msg)));
        }
        $location = $msg->getHeader('Location');
        $start = strpos($location, '?');
        if (false === $start) {
            throw new Exception(sprintf("POST to '%s' expects query string in '%s'\n%s",
                    $msg->getRequestUrl(), $location, $this->_dumpHttpMessage($msg)));
        }
        $queryString = substr($location, $start + 1);
        $query = new HttpQueryString(false, $queryString);
        $ticket = $query->get('ticket', HttpQueryString::TYPE_STRING, null);
        if (is_null($ticket)) {
            throw new Exception(sprintf("POST to '%s' expects 'ticket' in query string '%s'\n%s",
                    $msg->getRequestUrl(), $queryString, $this->_dumpHttpMessage($msg)));
        }
        return $this->_clearState($ticket);
    }

    private function _clearState($ticket = null) {
        $url = 'https://www.avangard.ru/clbAvn/faces/pages/firstpage';
        $request = new HttpRequest($url, HttpRequest::METH_GET, $this->options);
        if (!is_null($ticket)) {
            $request->setQueryData(array('ticket' => $ticket));
            $this->cookies = null;
        }
        $request->setCookies($this->cookies);
        $msg = $request->send();
        if ($request->getResponseCode() != 302) {
            throw new Exception(sprintf("GET from '%s' expects 302 status, got %d\n%s",
                    $msg->getRequestUrl(), $request->getResponseCode(), $this->_dumpHttpMessage($msg)));
        }
        $location = $msg->getHeader('Location');
        if ($location == 'https://www.avangard.ru/clbAvn/site.jsp') {
            throw new Exception(sprintf("GET from '%s' got incorrect location '%s'\n%s",
                    $msg->getRequestUrl(), $location, $this->_dumpHttpMessage($msg)));
        }
        if (is_null($this->cookies)) {
            $cookies = $request->getResponseCookies();
            $this->cookies = array_reduce($cookies,
                function (&$r, $c) {
                    $r = array_merge($r, $c->cookies);
                    return $r;
                }, array());
        }
        return $location;
    }

    private function _parseToken(HttpMessage $msg) {
        $matches = array();
        if (1 !== preg_match('/name="oracle.adf.faces.STATE_TOKEN" value="(?P<token>[^"]+)"/', $msg->getBody(), $matches) ||
                empty($matches['token'])) {
            throw new Exception(sprintf("Response from '%s' expects oracle.adf.faces.STATE_TOKEN in body\n%s",
                    $msg->getRequestUrl(), $this->_dumpHttpMessage($msg)));
        }
        return $matches['token'];
    }
    private function _parseSource(HttpMessage $msg, $expression) {
        $matches = array();
        if (1 !== preg_match($expression, $msg->getBody(), $matches) ||
                empty($matches['source'])) {
            throw new Exception(sprintf("Respnse from '%s' expects '%s' in body\n%s",
                    $msg->getRequestUrl(), $expression, $this->_dumpHttpMessage($msg)));
        }
        return $matches['source'];
    }

    public function export1C(DateTime $start = null, DateTime $end = null) {
        if (is_null($start))
            $start = new DateTime();
        if (is_null($end))
            $end = new DateTime();
        $location = $this->_clearState();
        $request = new HttpRequest($location, HttpRequest::METH_GET, $this->options);
        $request->setCookies($this->cookies);
        $msg = $request->send();
        if ($request->getResponseCode() != 200) {
            throw new Exception(sprintf("GET from '%s' expects 200 status, got %d\n%s",
                    $msg->getRequestUrl(), $request->getResponseCode(), $this->_dumpHttpMessage($msg)));
        }
        $token = $this->_parseToken($msg);
        $source = $this->_parseSource($msg,
                '/id="menu:menu_form:_id(?P<source>[^"]+)".*value="&#1042;&#1099;&#1087;&#1080;&#1089;&#1082;&#1080; &#1080; &#1086;&#1090;&#1095;&#1077;&#1090;&#1099;"/');
        $request = new HttpRequest($location, HttpRequest::METH_POST, $this->options);
        $request->setCookies($this->cookies);
        $request->setPostFields(array(
                'oracle.adf.faces.FORM' => 'menu:menu_form',
                'oracle.adf.faces.STATE_TOKEN' => $token,
                'event' => '',
                'source' => 'menu:menu_form:_id'.$source,
                'partialTargets' => '',
                'partial' => '',
            ));
        $msg = $request->send();
        $url = 'https://www.avangard.ru/clbAvn/faces/facelet-pages/con_acc_stat.jspx';
        if ($request->getResponseCode() != 302 ||
                $msg->getHeader('Location') != $url) {
            throw new Exception(sprintf("GET from '%s' expects 302 status and location '%s', got %d\n%s",
                    $msg->getRequestUrl(), $url, $request->getResponseCode(), $this->_dumpHttpMessage($msg)));
        }
        $request = new HttpRequest($url, HttpRequest::METH_GET, $this->options);
        $request->setCookies($this->cookies);
        $msg = $request->send();
        if ($request->getResponseCode() != 200) {
            throw new Exception(sprintf("GET from '%s' expects 200 status, got %d\n%s",
                    $msg->getRequestUrl(), $request->getResponseCode(), $this->_dumpHttpMessage($msg)));
        }
        $token = $this->_parseToken($msg);
        $source = $this->_parseSource($msg,
                '/source:[^d]+docslist:main:_id(?P<source>\d+)/');
        $request = new HttpRequest($url, HttpRequest::METH_POST, $this->options);
        $request->setCookies($this->cookies);
        $request->setPostFields(array(
                'docslist___jeniaPopupFrame' => '',
                'docslist:main:startdate' => $start->format('d.m.Y'),
                'docslist:main:finishdate' => $end->format('d.m.Y'),
                'docslist:main:selSort' => '2',
                'docslist:main:selVal' => '0',
                'docslist:main:clTbl:_s' => '0',
                'docslist:main:clTbl:_us' => '0',
                'docslist:main:clTbl:rangeStart' => '0',
                'docslist:main:accTbl:_s' => '0',
                'docslist:main:accTbl:_us' => '0',
                'docslist:main:accTbl:rangeStart' => '0',
                'oracle.adf.faces.FORM' => 'docslist',
                'oracle.adf.faces.STATE_TOKEN' => $token,
                'docslist:main:clTbl:_sm' => '',
                'docslist:main:accTbl:_sm' => '',
                'event' => '',
                'source' => 'docslist:main:_id'.$source,
            ));
        $msg = $request->send();
        if ($request->getResponseCode() != 200) {
            throw new Exception(sprintf("GET from '%s' expects 200 status, got %d\n%s",
                    $msg->getRequestUrl(), $request->getResponseCode(), $this->_dumpHttpMessage($msg)));
        }
        return mb_convert_encoding($msg->getBody(), 'utf-8', 'windows-1251');
    }
}
?>
