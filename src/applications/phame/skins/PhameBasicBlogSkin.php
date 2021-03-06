<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @task  paging    Paging
 * @task  internal  Internals
 * @group phame
 */
abstract class PhameBasicBlogSkin extends PhameBlogSkin {

  private $pager;

  public function processRequest() {
    $request = $this->getRequest();

    $content = $this->renderContent($request);

    if (!$content) {
      $content = $this->render404Page();
    }

    $content = array(
      $this->renderHeader(),
      $content,
      $this->renderFooter(),
    );

    $view = id(new PhabricatorBarePageView())
      ->setRequest($request)
      ->setController($this)
      ->setDeviceReady(true)
      ->setTitle($this->getBlog()->getName());

    if ($this->getPreview()) {
      $view->setFrameable(true);
    }

    $view->appendChild($content);

    $response = new AphrontWebpageResponse();
    $response->setContent($view->render());

    return $response;
  }

  public function getSkinName() {
    return get_class($this);
  }

  abstract protected function renderHeader();
  abstract protected function renderFooter();

  protected function renderPostDetail(PhamePostView $post) {
    return $post;
  }

  protected function renderPostList(array $posts) {
    $summaries = array();
    foreach ($posts as $post) {
      $summaries[] = $post->renderWithSummary();
    }

    $list = phutil_render_tag(
      'div',
      array(
        'class' => 'phame-post-list',
      ),
      id(new AphrontNullView())->appendChild($summaries)->render());

    $pager = $this->renderOlderPageLink().$this->renderNewerPageLink();
    if ($pager) {
      $pager = phutil_render_tag(
        'div',
        array(
          'class' => 'phame-pager',
        ));
    }

    return $list.$pager;
  }

  protected function render404Page() {
    return '<h2>404 Not Found</h2>';
  }

  final public function getResourceURI($resource) {
    $root = $this->getSpecification()->getRootDirectory();
    $path = $root.DIRECTORY_SEPARATOR.$resource;

    $data = Filesystem::readFile($path);
    $hash = PhabricatorHash::digest($data);
    $hash = substr($hash, 0, 6);
    $id = $this->getBlog()->getID();

    $uri = '/phame/r/'.$id.'/'.$hash.'/'.$resource;
    $uri = PhabricatorEnv::getCDNURI($uri);

    return $uri;
  }

/* -(  Paging  )------------------------------------------------------------- */


  /**
   * @task paging
   */
  public function getPageSize() {
    return 100;
  }


  /**
   * @task paging
   */
  protected function getOlderPageURI() {
    if ($this->pager) {
      $next = $this->pager->getNextPageID();
      if ($next) {
        return $this->getURI('older/'.$next.'/');
      }
    }
    return null;
  }


  /**
   * @task paging
   */
  protected function renderOlderPageLink() {
    $uri = $this->getOlderPageURI();
    if (!$uri) {
      return null;
    }
    return phutil_render_tag(
      'a',
      array(
        'class' => 'phame-page-link phame-page-older',
        'href'  => $uri,
      ),
      pht("\xE2\x80\xB9 Older"));
  }


  /**
   * @task paging
   */
  protected function getNewerPageURI() {
    if ($this->pager) {
      $next = $this->pager->getNextPageID();
      if ($next) {
        return $this->getURI('newer/'.$next.'/');
      }
    }
    return null;
  }


  /**
   * @task paging
   */
  protected function renderNewerPageLink() {
    $uri = $this->getNewerPageURI();
    if (!$uri) {
      return null;
    }
    return phutil_render_tag(
      'a',
      array(
        'class' => 'phame-page-link phame-page-newer',
        'href'  => $uri,
      ),
      pht("Newer \xE2\x80\xBA"));
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * @task internal
   */
  protected function renderContent(AphrontRequest $request) {
    $user = $request->getUser();

    $matches = null;
    $path = $request->getPath();
    if (preg_match('@^/post/(?P<name>.*)$@', $path, $matches)) {
      $post = id(new PhamePostQuery())
        ->setViewer($user)
        ->withBlogPHIDs(array($this->getBlog()->getPHID()))
        ->withPhameTitles(array($matches['name']))
        ->executeOne();

      if ($post) {
        $view = head($this->buildPostViews(array($post)));
        return $this->renderPostDetail($view);
      }
    } else {
      $pager = new AphrontCursorPagerView();

      if (preg_match('@^/older/(?P<before>\d+)/$@', $path, $matches)) {
        $pager->setBeforeID($matches['before']);
      } else if (preg_match('@^/newer/(?P<after>\d)/$@', $path, $matches)) {
        $pager->setAfterID($matches['after']);
      } else if (preg_match('@^/$@', $path, $matches)) {
        // Just show the first page.
      } else {
        return null;
      }

      $pager->setPageSize($this->getPageSize());

      $posts = id(new PhamePostQuery())
        ->setViewer($user)
        ->withBlogPHIDs(array($this->getBlog()->getPHID()))
        ->executeWithCursorPager($pager);

      $this->pager = $pager;

      if ($posts) {
        $views = $this->buildPostViews($posts);
        return $this->renderPostList($views);
      }
    }

    return null;
  }

  private function buildPostViews(array $posts) {
    assert_instances_of($posts, 'PhamePost');
    $user = $this->getRequest()->getUser();

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user);

    $phids = array();
    foreach ($posts as $post) {
      $engine->addObject($post, PhamePost::MARKUP_FIELD_BODY);
      $engine->addObject($post, PhamePost::MARKUP_FIELD_SUMMARY);

      $phids[] = $post->getBloggerPHID();
    }

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();

    $engine->process();

    $views = array();
    foreach ($posts as $post) {
      $view = id(new PhamePostView())
        ->setViewer($user)
        ->setSkin($this)
        ->setPost($post)
        ->setBody($engine->getOutput($post, PhamePost::MARKUP_FIELD_BODY))
        ->setSummary($engine->getOutput($post, PhamePost::MARKUP_FIELD_SUMMARY))
        ->setAuthor($handles[$post->getBloggerPHID()]);

      $post->makeEphemeral();
      if (!$post->getDatePublished()) {
        $post->setDatePublished(time());
      }

      $views[] = $view;
    }

    return $views;
  }

}
