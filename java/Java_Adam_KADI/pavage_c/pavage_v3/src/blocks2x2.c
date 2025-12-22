#include <stdio.h>
#include <stdlib.h>
#include "lego.h"
#include "matching.h"

static inline int idxy(int x,int y,int W){
    return y * W + x;
}

int mark_blocks_2x2(const Catalog *cat, const PlanV1 *plan,
                    int W, int H, int *mark){
    int count = 0;

    for(int y = 0; y + 1 < H; y++){
        for(int x = 0; x + 1 < W; x++){
            int u = idxy(x, y, W);
            int r = plan->choices[u].color_r;
            int g = plan->choices[u].color_g;
            int b = plan->choices[u].color_b;

            int u1 = idxy(x+1, y, W);
            int u2 = idxy(x, y+1, W);
            int u3 = idxy(x+1, y+1, W);

            if(plan->choices[u1].color_r == r && plan->choices[u2].color_r == r &&
               plan->choices[u3].color_r == r &&
               plan->choices[u1].color_g == g && plan->choices[u2].color_g == g &&
               plan->choices[u3].color_g == g &&
               plan->choices[u1].color_b == b && plan->choices[u2].color_b == b &&
               plan->choices[u3].color_b == b){
                
                for(int i=0; i<cat->count; i++){
                    if(cat->items[i].w==2 && cat->items[i].h==2 &&
                       cat->items[i].r==r && cat->items[i].g==g && cat->items[i].b==b){
                        mark[u] = 1;
                        count++;
                        break;
                    }
                }
            }
        }
    }
    return count;
}
