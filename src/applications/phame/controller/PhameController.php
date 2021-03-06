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
 * @group phame
 */
abstract class PhameController extends PhabricatorController {

  protected function renderSideNavFilterView() {

    $base_uri = new PhutilURI($this->getApplicationURI());

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI($base_uri);

    $nav->addLabel('Create');
    $nav->addFilter('post/new',   'New Post');
    $nav->addFilter('blog/new',   'New Blog');

    $nav->addSpacer();

    $nav->addLabel('Posts');
    $nav->addFilter('post/draft', 'My Drafts');
    $nav->addFilter('post',       'My Posts');
    $nav->addFilter('post/all',   'All Posts');

    $nav->addSpacer();

    $nav->addLabel('Blogs');
    $nav->addFilter('blog/user',  'Joinable Blogs');
    $nav->addFilter('blog/all',   'All Blogs');

    $nav->selectFilter(null);

    return $nav;
  }

  protected function renderPostList(
    array $posts,
    PhabricatorUser $user,
    $nodata) {
    assert_instances_of($posts, 'PhamePost');

    $list = id(new PhabricatorObjectItemListView())
      ->setNoDataString($nodata);

    foreach ($posts as $post) {
      $item = id(new PhabricatorObjectItemView())
        ->setHeader($post->getTitle())
        ->setHref($this->getApplicationURI('post/view/'.$post->getID().'/'))
        ->addDetail(
          pht('Blogger'),
          $this->getHandle($post->getBloggerPHID())->renderLink())
        ->addDetail(
          pht('Blog'),
          $post->getBlog()
            ? $this->getHandle($post->getBlog()->getPHID())->renderLink()
            : '-');

      if ($post->isDraft()) {
        $item->addAttribute(pht('Draft'));
      } else {
        $date_published = phabricator_datetime(
          $post->getDatePublished(),
          $user);
        $item->addAttribute(pht('Published on %s', $date_published));
      }

      $list->addItem($item);
    }

    return $list;
  }
}
